<?php if(($pagination['total']??0)>0):$page=(int)$pagination['page'];$pages=(int)$pagination['pages'];$pageKey=(string)($pagination['page_key']??'page');$sizeKey=(string)($pagination['size_key']??'per_page');$total=(int)$pagination['total'];$sizes=array_values(array_filter([10,25,50,75,100],static fn(int $size):bool=>$size<=max($total,10)));$sizes=array_values(array_unique($sizes));sort($sizes);$url=static function(int $target)use($pageKey):string{$query=$_GET;$query[$pageKey]=$target;return base_url('index.php?'.http_build_query($query));};?>
<nav class="data-pagination" aria-label="Paginación de resultados">
    <p>Mostrando <strong><?=$pagination['to']?></strong> de <strong><?=$pagination['total']?></strong></p>
    <form method="get" class="data-pagination-size"><?php foreach($_GET as $key=>$value):if($key===$sizeKey||$key===$pageKey||is_array($value))continue;?><input type="hidden" name="<?=e((string)$key)?>" value="<?=e((string)$value)?>"><?php endforeach;?><label><span>Mostrar</span><select name="<?=e($sizeKey)?>" onchange="this.form.submit()" aria-label="Cantidad de resultados visibles" data-dropdown-placement="top"><?php foreach($sizes as $size):?><option value="<?=$size?>" <?=$pagination['per_page']===$size?'selected':''?>><?=$size?></option><?php endforeach;?></select></label></form>
    <div class="data-pagination-pages">
        <a href="<?=e($url(max(1,$page-1)))?>" class="<?=$page<=1?'is-disabled':''?>" aria-label="Página anterior"><i class="fa-solid fa-chevron-left"></i></a>
        <?php
        if($pages<=5)$numbers=range(1,$pages);
        elseif($page<=3)$numbers=[1,2,3,'ellipsis',$pages];
        elseif($page>=$pages-2)$numbers=[1,'ellipsis',$pages-2,$pages-1,$pages];
        else $numbers=[1,'ellipsis',$page-1,$page,$page+1,'ellipsis',$pages];
        foreach($numbers as $number):
            if($number==='ellipsis'):?><span class="pagination-ellipsis" aria-hidden="true">…</span><?php
            else:?><a href="<?=e($url($number))?>" class="<?=$number===$page?'is-active':''?>" <?=$number===$page?'aria-current="page"':''?>><?=$number?></a><?php
            endif;
        endforeach;?>
        <a href="<?=e($url(min($pages,$page+1)))?>" class="<?=$page>=$pages?'is-disabled':''?>" aria-label="Página siguiente"><i class="fa-solid fa-chevron-right"></i></a>
    </div>
</nav>
<?php endif;?>
