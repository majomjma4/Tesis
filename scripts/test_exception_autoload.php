<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
require APP_PATH . '/Core/Autoloader.php';

Autoloader::register();

$classes = [
    'CalendarEventException',
    'ProjectAdjustmentRequestException',
    'ProjectAuthorException',
    'ProjectDocumentArchiveException',
    'ProjectDocumentVersionException',
    'ProjectDraftFileConflictException',
    'ProjectDraftRegistrationException',
    'ProjectStatusTransitionException',
    'ProjectStudentPublicationException',
    'ProjectTutoringException',
    'RepositoryDirectProjectException',
    'SettingsEncryptionException',
    'StudentProjectInformationException',
    'StudentProjectSubmissionException',
    'SupportMaterialAccessException',
    'TemporaryPasswordPolicyException',
    'ThesisDefenseException',
    'ThesisDefenseResultException',
    'ThesisDefenseScheduleException',
    'ThesisPublicationException',
    'ThesisTribunalException',
];

$failures = [];
foreach ($classes as $class) {
    if (!class_exists($class)) {
        $failures[] = $class . ' no se pudo resolver';
        continue;
    }

    $reflection = new ReflectionClass($class);
    if (basename((string) $reflection->getFileName(), '.php') !== $class) {
        $failures[] = $class . ' no está en su archivo dedicado';
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

printf("Exception autoload OK (%d clases)\n", count($classes));
