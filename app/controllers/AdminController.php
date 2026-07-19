<?php
declare(strict_types=1);

final class AdminController
{
    public function module(string $section): void
    {
        $modules=['users'=>['Usuarios','fa-users','La gestión de cuentas se habilitará en la Fase 3.'],'academic'=>['Gestión académica','fa-graduation-cap','Periodos, matrículas y catálogos se habilitarán en la Fase 4.'],'reports'=>['Reportes','fa-chart-column','Los reportes administrativos se habilitarán al completar los módulos de datos.'],'settings'=>['Configuración','fa-gear','Los parámetros institucionales se incorporarán en la Fase 6.'],'trash'=>['Papelera','fa-trash-can','La restauración y purga a 60 días se incorporará en la Fase 7.']];
        if(!isset($modules[$section])){$this->module('users');return;}$item=$modules[$section];
        View::render('admin/module-pending',['currentPage'=>'admin-'.$section,'title'=>$item[0].' | Administración','bodyClass'=>'admin-page','pageStyles'=>[asset('css/admin-access.css')],'moduleTitle'=>$item[0],'moduleIcon'=>$item[1],'moduleMessage'=>$item[2]]);
    }
}
