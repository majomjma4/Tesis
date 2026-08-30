<?php

declare(strict_types=1);

/** Generates private, checksum-addressed PDF review representations for DOCX files. */
final class DocumentPreviewConversionService
{
    private const DEFAULT_TIMEOUT = 45;
    private const LOCK_WAIT_SECONDS = 2;
    private const FAILURE_COOLDOWN_SECONDS = 300;
    private static ?bool $availability = null;

    public function convertFile(string $source, int $projectId, int $fileId, string $identity): array
    {
        if (!is_file($source) || !is_readable($source)) throw new RuntimeException('El documento fuente no está disponible.');
        return $this->convert($source, $projectId, $fileId, $identity);
    }

    /** The stream is copied only to an isolated temporary directory; ZIP entries are never persisted as project files. */
    public function convertStream($stream, int $projectId, int $fileId, string $identity): array
    {
        $work = $this->workDirectory();
        $source = $work . DIRECTORY_SEPARATOR . 'source.docx';
        try {
            rewind($stream);
            $target = fopen($source, 'wb');
            if ($target === false || stream_copy_to_stream($stream, $target) === false) throw new RuntimeException('No fue posible preparar el documento temporal.');
            fclose($target);
            return $this->convert($source, $projectId, $fileId, $identity, $work);
        } catch (Throwable $error) {
            $this->removeTree($work);
            throw $error;
        }
    }

    public function cachedPath(int $projectId, int $fileId, string $identity): ?string
    {
        $path = $this->finalPath($projectId, $fileId, $identity);
        return $this->validPdf($path) ? $path : null;
    }

    public function discardCached(int $projectId, int $fileId, string $identity): void
    {
        if ($projectId < 1 || $fileId < 1 || !preg_match('/^[a-f0-9]{64}$/', $identity)) return;
        $cached = $this->finalPath($projectId, $fileId, $identity);
        $failure = $this->failurePath($projectId, $fileId, $identity);
        if (is_file($cached)) @unlink($cached);
        if (is_file($failure)) @unlink($failure);
    }

    public function isAvailable(): bool
    {
        if (self::$availability !== null) return self::$availability;
        $path = $this->executable();
        return self::$availability = ($path !== '' && is_file($path) && is_readable($path));
    }

    private function convert(string $source, int $projectId, int $fileId, string $identity, ?string $existingWork = null): array
    {
        if (!$this->isAvailable()) throw new RuntimeException('LibreOffice no está disponible.');
        if ($projectId < 1 || $fileId < 1 || !preg_match('/^[a-f0-9]{64}$/', $identity)) throw new InvalidArgumentException('La identidad de vista previa no es válida.');
        $final = $this->finalPath($projectId, $fileId, $identity);
        if ($this->validPdf($final)) return ['path'=>$final, 'cached'=>true];

        $lock = $this->acquirePreviewLock($projectId, $fileId, $identity);
        $work = $existingWork ?? $this->workDirectory();
        $ownsWork = $existingWork === null;
        $profile = $work . DIRECTORY_SEPARATOR . 'profile';
        $output = $work . DIRECTORY_SEPARATOR . 'output';
        $failure = $this->failurePath($projectId, $fileId, $identity);
        $process = null;
        try {
            if ($this->hasRecentFailure($failure)) throw new RuntimeException('La vista previa no está disponible temporalmente.');
            if ($this->validPdf($final)) { @unlink($failure); return ['path'=>$final, 'cached'=>true]; }
            if (!is_dir($profile) && !mkdir($profile, 0700, true) && !is_dir($profile)) throw new RuntimeException('No fue posible preparar el perfil temporal.');
            if (!is_dir($output) && !mkdir($output, 0700, true) && !is_dir($output)) throw new RuntimeException('No fue posible preparar la salida temporal.');
            $temporarySource = $work . DIRECTORY_SEPARATOR . 'source.docx';
            if ($source !== $temporarySource) {
                if (!copy($source, $temporarySource)) throw new RuntimeException('No fue posible copiar el documento para conversión.');
            }
            $uri = 'file:///' . str_replace('\\', '/', $profile);
            $command = [$this->executable(), '--headless', '--nologo', '--nodefault', '--nofirststartwizard', '-env:UserInstallation=' . $uri, '--convert-to', 'pdf', '--outdir', $output, $temporarySource];
            $started = microtime(true);
            $process = proc_open($command, [1=>['pipe','w'], 2=>['pipe','w']], $pipes, $work, null, ['bypass_shell'=>true]);
            if (!is_resource($process)) throw new RuntimeException('No fue posible iniciar LibreOffice.');
            stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
            $stdout = ''; $stderr = ''; $timeout = $this->timeout(); $status = proc_get_status($process);
            while ($status['running']) {
                $stdout .= stream_get_contents($pipes[1]) ?: ''; $stderr .= stream_get_contents($pipes[2]) ?: '';
                if ((microtime(true) - $started) > $timeout) { $this->terminateProcessTree($process, (int)($status['pid'] ?? 0)); fclose($pipes[1]); fclose($pipes[2]); @proc_close($process); $process=null; throw new RuntimeException('La conversión excedió el tiempo máximo permitido.'); }
                usleep(100000); $status = proc_get_status($process);
            }
            $stdout .= stream_get_contents($pipes[1]) ?: ''; $stderr .= stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process); $process=null;
            $temporaryPdf = $output . DIRECTORY_SEPARATOR . 'source.pdf';
            if ($exit !== 0 || !$this->validPdf($temporaryPdf)) {
                error_log('Document preview conversion failed: exit=' . $exit . '; stderr=' . substr($stderr, 0, 2000) . '; stdout=' . substr($stdout, 0, 1000));
                throw new RuntimeException('LibreOffice no produjo un PDF válido.');
            }
            $directory = dirname($final);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('No fue posible preparar el almacenamiento de vistas previas.');
            if (!$this->validPdf($final) && !@rename($temporaryPdf, $final) && !$this->validPdf($final)) throw new RuntimeException('No fue posible promover la vista previa.');
            @unlink($failure);
            return ['path'=>$final, 'cached'=>false, 'elapsed_ms'=>(int) round((microtime(true) - $started) * 1000)];
        } catch (Throwable $error) {
            $this->writeFailure($failure);
            throw $error;
        } finally {
            if (is_resource($process)) { $status=proc_get_status($process); $this->terminateProcessTree($process, (int)($status['pid'] ?? 0)); @proc_close($process); }
            if ($ownsWork || $existingWork !== null) $this->removeTree($work);
            $this->releasePreviewLock($lock);
        }
    }

    private function executable(): string { $config = $GLOBALS['config'] ?? []; return trim((string)($config['libreoffice_path'] ?? '')); }
    private function timeout(): int { $config = $GLOBALS['config'] ?? []; return max(5, min(180, (int)($config['libreoffice_timeout_seconds'] ?? self::DEFAULT_TIMEOUT))); }
    private function finalPath(int $projectId, int $fileId, string $identity): string { return ROOT_PATH . '/storage/private/project-previews/' . $projectId . '/' . $fileId . '_' . $identity . '.pdf'; }
    private function workDirectory(): string { $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'project-preview-' . bin2hex(random_bytes(16)); if (!mkdir($path, 0700, true) && !is_dir($path)) throw new RuntimeException('No fue posible preparar el directorio temporal.'); return $path; }
    private function acquirePreviewLock(int $projectId, int $fileId, string $identity)
    {
        $path=ROOT_PATH.'/storage/private/project-previews/.locks/'.$projectId.'/'.$fileId.'_'.$identity.'.lock'; $directory=dirname($path);
        if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory)) throw new RuntimeException('No fue posible reservar la vista previa.');
        $handle=fopen($path,'c+'); if($handle===false) throw new RuntimeException('No fue posible reservar la vista previa.');
        $deadline=microtime(true)+self::LOCK_WAIT_SECONDS;
        do { if(flock($handle,LOCK_EX|LOCK_NB)) return $handle; usleep(100000); } while(microtime(true)<$deadline);
        fclose($handle); throw new RuntimeException('La vista previa ya está en proceso.');
    }
    private function releasePreviewLock($handle): void { if(is_resource($handle)){@flock($handle,LOCK_UN);@fclose($handle);} }
    public function clearFailure(int $projectId, int $fileId, string $identity): void { $failure = $this->failurePath($projectId, $fileId, $identity); if (is_file($failure)) @unlink($failure); }
    private function failurePath(int $projectId,int $fileId,string $identity): string { return ROOT_PATH.'/storage/private/project-previews/.failures/'.$projectId.'/'.$fileId.'_'.$identity.'.json'; }
    private function hasRecentFailure(string $path): bool { return is_file($path)&&((time()-(int)@filemtime($path))<self::FAILURE_COOLDOWN_SECONDS); }
    private function writeFailure(string $path): void { $directory=dirname($path);if(!is_dir($directory))@mkdir($directory,0700,true);@file_put_contents($path,json_encode(['failed_at'=>time()],JSON_UNESCAPED_SLASHES),LOCK_EX); }
    private function terminateProcessTree($process,int $pid): void { if(PHP_OS_FAMILY==='Windows'&&$pid>0){$output=[];$code=0;@exec('taskkill.exe /PID '.(int)$pid.' /T /F 2>NUL',$output,$code);} if(is_resource($process))@proc_terminate($process); }
    private function validPdf(string $path): bool { if (!is_file($path) || filesize($path) < 5) return false; $handle = fopen($path, 'rb'); if ($handle === false) return false; $signature = fread($handle, 5); fclose($handle); return $signature === '%PDF-'; }
    private function removeTree(string $path): void { if (!is_dir($path)) return; $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); } @rmdir($path); }
}
