<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

class Banners_model extends MY_Model
{

    public function __construct()
    {
        parent::__construct();
        
        $this->table = 'banners';
        $this->tool_name_id = 1;
    }
}

/* Конец файла Banners_model.php */
/* Расположение: ./application/models/Banners_model.php */