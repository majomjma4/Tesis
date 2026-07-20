<?php
declare(strict_types=1);

final class PaginationService
{
    private const ALLOWED_SIZES=[10,25,50,100];

    public static function request(string $pageKey='p',string $sizeKey='per_page',int $defaultSize=25):array
    {
        $page=max(1,(int)($_GET[$pageKey]??1));
        $size=(int)($_GET[$sizeKey]??$defaultSize);
        if(!in_array($size,self::ALLOWED_SIZES,true))$size=$defaultSize;
        return compact('page','size','pageKey','sizeKey');
    }

    public static function run(PDO $db,string $countSql,string $dataSql,array $params,array $request):array
    {
        $count=$db->prepare($countSql);$count->execute($params);$total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$request['size']));$page=min($request['page'],$pages);$offset=($page-1)*$request['size'];
        $list=$db->prepare($dataSql.' LIMIT :pagination_limit OFFSET :pagination_offset');
        foreach($params as $key=>$value)$list->bindValue(':'.$key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $list->bindValue(':pagination_limit',$request['size'],PDO::PARAM_INT);$list->bindValue(':pagination_offset',$offset,PDO::PARAM_INT);$list->execute();
        return ['items'=>$list->fetchAll(),'pagination'=>['page'=>$page,'per_page'=>$request['size'],'total'=>$total,'pages'=>$pages,'from'=>$total?$offset+1:0,'to'=>min($offset+$request['size'],$total),'page_key'=>$request['pageKey'],'size_key'=>$request['sizeKey']]];
    }
}
