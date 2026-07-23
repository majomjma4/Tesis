<?php if(($pagination['total']??0)>10):$page=(int)$pagination['page'];$pages=(int)$pagination['pages'];$pageKey=(string)$pagination['page_key'];$sizeKey=(string)$pagination['size_key'];$url=static function(int $target)use($pageKey):string{$query=$_GET;$query[$pageKey]=$target;return base_url('index.php?'.http_build_query($query));};?>
<nav class="data-pagination" aria-label="Paginación de resultados">
    <p>Mostrando <strong><?=$pagination['to']?></strong> de <strong><?=$pagination['total']?></strong></p>
    <form method="get" class="data-pagination-size"><?php foreach($_GET as $key=>$value):if($key===$sizeKey||$key===$pageKey||is_array($value))continue;?><input type="hidden" name="<?=e((string)$key)?>" value="<?=e((string)$value)?>"><?php endforeach;?><label><span>Mostrar</span><select name="<?=e($sizeKey)?>" onchange="this.form.submit()" aria-label="Cantidad de resultados visibles" data-dropdown-placement="top"><?php foreach([10,25,50,75,100] as $size):if($size>$pagination['total'])continue;?><option value="<?=$size?>" <?=$pagination['per_page']===$size?'selected':''?>><?=$size?></option><?php endforeach;?></select></label></form>
    <div class="data-pagination-pages">
        <a href="<?=e($url(max(1,$page-1)))?>" class="<?=$page<=1?'is-disabled':''?>" aria-label="Página anterior"><i class="fa-solid fa-chevron-left"></i></a>
        <?php $start=max(1,min($page-2,$pages-4));$end=min($pages,$start+4);for($number=$start;$number<=$end;$number++):?><a href="<?=e($url($number))?>" class="<?=$number===$page?'is-active':''?>" <?=$number===$page?'aria-current="page"':''?>><?=$number?></a><?php endfor;?>
        <a href="<?=e($url(min($pages,$page+1)))?>" class="<?=$page>=$pages?'is-disabled':''?>" aria-label="Página siguiente"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
</nav>
<?php endif;?>
