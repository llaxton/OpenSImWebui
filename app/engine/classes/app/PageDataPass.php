<?php
if (!defined("OSWUI"))
{
    echo 'Sorry this page cann not be accessed directly';
 die();
}
class PageDataPass 
{
    var $tobereplaced = array('[b]',['/b]']);
    var $replacewith = array('<b>','<b/>');
    public function GeneralPass($data)
    {
         return str_replace(array($this->tobereplaced), array($this->replacewith), $data);
    }
    public function PagePass($replace,$replacedwith,$data)
    {
        
    }
            
}