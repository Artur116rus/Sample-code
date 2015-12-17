<?php

/**
 * Суперкласс для моделей инструментов.
 *
 * Необходимо проинициализировать $table и $tool_name_id в конструкторе дочернего класса.
 * Использовать только для инструментов вебинара.
 */
class MY_Model extends CI_Model
{

    protected $table = '';

    protected $tool_name_id = 0;

    protected function switch_db($webinar_id)
    {
        if (! $this->db->db_select('webinar_' . $webinar_id)) die('Вебинар не существует');
    }

    protected function log($data)
    {
        $newdata = array('tool_name_id' => $this->tool_name_id,'tool_id' => $data['tool_id'],
            'date' => date('Y-m-d H:i:s'),'status' => $data['status']);
        $this->db->insert('tools_log', $newdata);
    }

    public function get($webinar_id, $id = 0, $filter = array())
    {
        $this->switch_db($webinar_id);
        $where_clause = ($id == 0 ? array() : array('id' => $id)) + $filter;
        $query = $this->db->get_where($this->table, $where_clause);
        return ($id == 0 ? $query->result_array() : $query->row_array());
    }

    public function add($webinar_id, $data)
    {
        $this->switch_db($webinar_id);
        $data['status'] = 0;
        $data['date'] = date('Y-m-d H:i:s');
        $this->db->insert($this->table, $data);
    }

    public function delete($webinar_id, $id)
    {
        $this->switch_db($webinar_id);
        $this->db->delete($this->table, array('id' => $id));
    }

    public function edit($webinar_id, $id, $data)
    {
        $this->switch_db($webinar_id);
        $data['date'] = date('Y-m-d H:i:s');
        $this->db->update($this->table, $data, array('id' => $id));
    }

    public function update_status($webinar_id, $data)
    {
        $this->switch_db($webinar_id);
        $newdata = array('status' => $data['status']);
        $this->db->update($this->table, $newdata, array('id' => $data['tool_id']));
        $this->log($data);
    }
}

/* Конец файла MY_Model.php */
/* Расположение: ./application/core/MY_Model.php */