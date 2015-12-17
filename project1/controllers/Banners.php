<?php
if (! defined('BASEPATH')) exit('No direct script access allowed');

class Banners extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->library('view_lib');
        $this->load->model('banners_model');
        $this->load->model('webinars_model');
        $this->lang->load('auth', $this->language_lib->detect());
        $id = $this->input->cookie('user_id');
        if (! $id or empty($id)) redirect('auth');
    }
    
   
    public function index($webinar_id = 0)
    {
        $data['webinar_id'] = $webinar_id;
        $data['section'] = 'tools';
        $data['page_name'] = 'banners';
        $data['page'] = 'webinars/banners/all_banners';
        $data['banners'] = $this->banners_model->get($webinar_id);
        $this->view_lib->webinars($data);
    }

    public function add($webinar_id = 0)
    {
        if (isset($_POST) and sizeof($_POST) > 0)
        {
            $post['title'] = $this->input->post('NameBanners');
            $post['url'] = $this->input->post('UrlBanners');
            $post['type'] = 0;
            $post['block_id'] = $this->input->post('BlockId');
            
            if ($post['title'] == null)
            {
                redirect('/webinars/edit/' . $webinar_id . '/banners/add?error=2'); // Введите имя
            }
			elseif($post['block_id'] == '')
			{
				redirect('/webinars/edit/' . $webinar_id . '/banners/add?error=3');
			}
            
            $user_id = $this->input->cookie('user_id');
            
            $dir = 'assets/images/users/' . $user_id;
            if (! file_exists($dir)) mkdir($dir);
            $dir .= '/banners';
            if (! file_exists($dir)) mkdir($dir);
            
            $filename = $_FILES['userfile']['name'];
            $ext = substr($filename, 1 + strrpos($filename, "."));
            $post['image'] = md5(uniqid(rand(), 1)) . "." . $ext;
            
            $config['file_name'] = $post['image'];
            $config['upload_path'] = './assets/images/users/' . $user_id . '/banners/';
            $config['allowed_types'] = 'gif|jpg|png';
            $config['max_size'] = '2048';
            $config['max_width'] = '2000';
            $config['max_height'] = '2000';
            $this->load->library('upload', $config);
            
            if (! $this->upload->do_upload())
            {
                redirect('/webinars/edit/' . $webinar_id . '/banners/add?error=1');
            }
            
            $this->banners_model->add($webinar_id, $post);
            redirect('/webinars/edit/' . $webinar_id . '/banners/');
        }
        else
        {
            $data['webinar_id'] = $webinar_id;
            $data['section'] = 'tools';
            $data['page_name'] = 'banners';
            $data['page'] = 'webinars/banners/add_banners';
            $webinar = $this->webinars_model->get_webinar($webinar_id);
            $data['blocks'] = $this->webinars_model->get_template_blocks($webinar['tmp_id'], 1);
            $this->view_lib->webinars($data);
        }
    }
    
    public function add_ajax($webinar_id = 0)
    {
		$filename = $_FILES['image_file']['name'];
		$ext = substr($filename, 1 + strrpos($filename, "."));
		$image_filename = md5(uniqid(rand(), 1)) . "." . $ext;
		$user_id = $this->input->cookie('user_id');
		$post['title'] = $_POST['name_banner'];
		$post['url'] = $_POST['url_banner'];
		$post['image'] = $image_filename;
		$post['type'] = 1;
		$post['block_id'] = $_POST['BlockId'];
		
		$dir = 'assets/images/users/' . $user_id;
		if (! file_exists($dir)) mkdir($dir);
		$dir .= '/banners';
		if (! file_exists($dir)) mkdir($dir);
		$upload_dir = './assets/images/users/' . $user_id . '/banners/';
		$upload_file = $upload_dir . $image_filename;
		
		if (! move_uploaded_file($_FILES["image_file"]["tmp_name"], $upload_file))
		{
			echo json_encode('Во время загрузки произошли ошибки. Попробуйте повторить');
			exit();
		}
		
		$this->banners_model->add($webinar_id, $post);
   
		$data['webinar_id'] = $webinar_id;
		$data['banner_id'] = $this->db->insert_id();
		$data['user_id'] = $this->input->cookie('user_id');
		$data['title'] = $_POST['name_banner'];
		$data['url'] = $_POST['url_banner'];
		$data['image'] = $image_filename;
		$data['type'] = 1;
		$data['status'] = 0;
		$data['date'] = date("Y-m-d H:i:s");
		$data['block_id'] = $_POST['BlockId'];
		echo json_encode($data);  
    }

    public function delete($webinar_id, $id)
    {
        $this->banners_model->delete($webinar_id, $id);
        redirect('/webinars/edit/' . $webinar_id . '/banners/');
    }

    public function edit($webinar_id, $id)
    {
        if (isset($_POST) and sizeof($_POST) > 0)
        {
            $post['title'] = $this->input->post('name_banners');
            $post['url'] = $this->input->post('url_banners');
            $post['block_id'] = $this->input->post('BlockId');
			
            if ($_FILES['userfile']['error'] == UPLOAD_ERR_OK)
            {
                $user_id = $this->input->cookie('user_id');
                $filename = $_FILES['userfile']['name'];
                $ext = substr($filename, 1 + strrpos($filename, "."));
                $post['image'] = md5(uniqid(rand(), 1)) . "." . $ext;
                
                $config['file_name'] = $post['image'];
                $config['upload_path'] = "./assets/images/users/" . $user_id . "/banners";
                $config['allowed_types'] = 'gif|jpg|png';
                $config['max_size'] = '2048';
                $config['max_width'] = '2000';
                $config['max_height'] = '2000';
                $this->load->library('upload', $config);
                
                if (! $this->upload->do_upload())
                {
                    redirect('/webinars/edit/' . $webinar_id . '/banners/add?error=1'); // Не удалось загрузить картинку
                }
            }
            
            $this->banners_model->edit($webinar_id, $id, $post);
            redirect('/webinars/edit/' . $webinar_id . '/banners/');
        }
        else
        {
            $data['webinar_id'] = $webinar_id;
            $data['section'] = 'tools';
            $data['page_name'] = 'banners';
            $data['page'] = 'webinars/banners/edit_banners';
            $data['banner'] = $this->banners_model->get($webinar_id, $id);
            $webinar = $this->webinars_model->get_webinar($webinar_id);
            $data['blocks'] = $this->webinars_model->get_template_blocks($webinar['tmp_id'], 1);
            $this->view_lib->webinars($data);
        }
    }
}