<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Banner extends CI_Controller 
{
	public $outputData;

	function __construct()
	{
		parent::__construct();
		if(!isAdmin())
		{
			redirect_admin('login');
		}
		$this->load->model('admin/banner_model');
		$this->load->model('admin/page_model');
		$this->load->library('s3');
	}
	
	function index()
	{
		$start = $this->uri->segment(3,0);
		if($start == 0)
		{
			$banner_filter_array = array('banner_campaign_id'=>'','banner_status'=>'','banner_campaignName'=>'');
			$this->session->unset_userdata($banner_filter_array);
		}
		$where=array();
		$banner_pagination_array = array();
		if(($this->input->post('filter_campaign_id') && $this->input->post('filter_campaign_id') != '') ||  $this->input->post('filter_status'))
		{
			$banner_filter_array = array('banner_campaign_id'=>'','banner_status'=>'','banner_campaignName'=>'');
			$this->session->unset_userdata($banner_filter_array);
		}
		if($this->input->post('filter_campaign_id') && $this->input->post('filter_campaign_id') != '')
		{
			$where['as_banner.campaign_id'] = $this->input->post('filter_campaign_id');
			$this->outputData['filter_campaign'] = $this->input->post('filter_campaign');
			$this->outputData['filter_campaign_id'] = $this->input->post('filter_campaign_id');
			$this->session->set_userdata(array('banner_campaignName'=>$this->input->post('filter_campaign')));
			$banner_pagination_array['banner_campaign_id']=$this->input->post('filter_campaign_id');
		}
		
		$filter = 'All';
		if(isset($_POST['filter_status']))
		{
			$filter = $_POST['filter_status'];
		}
		if(isset($filter) && $filter !='All')
		{
			$where['as_banner.status'] = $this->input->post('filter_status');
			$this->outputData['filter_status'] = $this->input->post('filter_status');
			$banner_pagination_array['banner_status']=$this->input->post('filter_status');
		}
		else
		{
			$this->outputData['filter_status'] = 'All';
		}
		$this->session->set_userdata($banner_pagination_array);
		
		if(isset($this->session->userdata['banner_campaign_id']) || isset($this->session->userdata['banner_status']))
		{
			if(isset($this->session->userdata['banner_campaign_id']) && $this->session->userdata['banner_campaign_id']!= '')
			{
				$where['as_banner.campaign_id'] = $this->session->userdata['banner_campaign_id'];
				$this->outputData['filter_campaign'] = $this->session->userdata['banner_campaignName'];
				$this->outputData['filter_campaign_id'] = $this->session->userdata['banner_campaign_id'];
			}
			if(isset($this->session->userdata['banner_status']) && $this->session->userdata['banner_status']!= '')
			{
				$where['as_banner.status'] = $this->session->userdata['banner_status'];
				$this->outputData['filter_status'] = $this->session->userdata['banner_status'];
			}
		}
		$records = $this->banner_model->getBanners($where);
		
		$page_rows = $this->config->item('admin_listing_limit');
		$limit[0] = $page_rows;
		
		if($start > 0)
		{
			$limit[1]	= ($start-1) * $page_rows;
			$this->outputData['start'] = ($start-1) * $page_rows;
		} else {
			$limit[1]	= $start * $page_rows;
			$this->outputData['start'] = $start * $page_rows;
		}
		
		$order[0] = 'as_banner.date_added';
		$order[1] = 'DESC';
		
		$this->outputData['banners'] = $this->banner_model->getBanners($where,NULL,NULL,$limit,$order);
		
		$this->load->library('pagination');
		$config['base_url'] = admin_url('banner');
		$config['total_rows'] = $records->num_rows(); 
		$config['per_page'] = $page_rows;
		$config['cur_page'] = $start;
		$this->outputData['total_pages'] = ceil($config['total_rows']/$this->config->item('admin_listing_limit'));
		$this->pagination->initialize($config);
		$this->outputData['pagination'] = $this->pagination->create_links2(false,'index');
		$this->smarty->view('admin/banner/index.tpl', $this->outputData);
	}
	
	function add() { 
	    $this->load->model('admin/category_model');
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit')) {
			$this->form_validation->set_rules('banner_type_id','Banner Type(Size)','required|trim|xss_clean');
			$this->form_validation->set_rules('campaign_id','Campaign','required|trim|xss_clean');
			$this->form_validation->set_rules('destination_url','Destination URL','required|trim|xss_clean');
			$this->form_validation->set_rules('status','','trim|xss_clean');
			if($this->form_validation->run()) {
				$insertData = array();
				$insertData['campaign_id']			= $this->input->post('campaign_id');
				$insertData['banner_type_id']		= $this->input->post('banner_type_id');
				$insertData['destination_url']		= $this->input->post('destination_url');
				$campaign_id = $this->input->post('campaign_id');
				$campaigns = $this->banner_model->getBannerCampaigns(array('campaign_id'=>$this->input->post('campaign_id')),'campaign_budget');
				if(isset($campaigns) && $campaigns->num_rows()>0) {
					$campaign = $campaigns->row();
				}
		       
				$insertData['date_added'] 				= date('Y-m-d');
				$insertData['status'] 					= $this->input->post('status');
			    $valid_formats = array("jpg", "png", "gif", "bmp","jpeg","PNG","JPG","JPEG","GIF","BMP");
                if($_FILES['banner_image_1']['name']!='') {               	
                    $ext = $this->getExtension($_FILES['banner_image_1']['name']);
                    if(in_array($ext,$valid_formats)) {
                        $bucket= $this->config->item('bucket_name');
                        $config = array(
                            'access_key'     => $this->config->item('access_key'),
                            'secret_key'     => $this->config->item('secret_key'),
                            'use_ssl'        => false
                        );
                        $s3 = new S3($config);
                        $actual_image_name = time().".".$ext;
                        $tmp = $_FILES['banner_image_1']['tmp_name'];
                        $s3->putObjectFile($tmp, $bucket , 'banner/'.$campaign_id.'/'.$actual_image_name, S3::ACL_PUBLIC_READ);
                        $insertData['banner_file'] = $actual_image_name;
                    }
                }
                if($this->input->post('category_type')==1) {
                  	 $insertData['is_category'] = 0;
                } else {
                	$insertData['is_category'] = 1;
                }
				if($this->banner_model->addBanner($insertData)) {
					$banner_id = $this->db->insert_id();
					$insertBannerDisplay = array();
					$insertBannerDisplay['banner_id'] = $banner_id;						
					$banner_type_id		= $this->input->post('banner_type_id');
					if($banner_type_id==2 || $banner_type_id==3){
  						if($this->input->post('category_type')==2){
  							$subcategories_array=$this->input->post('sub_category_page');
  							foreach($subcategories_array as $all_subcat){
  								$insertBannerDisplay['category_id'] = $all_subcat;
  							    $this->banner_model->addBannerDisplayProductPage($insertBannerDisplay);
  							}
  						}
					} else if ($banner_type_id==4) {
						if($this->input->post('cart_page')==1){
							$insertBannerDisplay['display_id'] = 1;
  							$this->banner_model->addBanner_displayPage($insertBannerDisplay);
						}
						if($this->input->post('allcategory_page')==2){
							$insertBannerDisplay['display_id'] = 2;
  							$this->banner_model->addBanner_displayPage($insertBannerDisplay);

						}
					}
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Added.'));
					redirect_admin('banner');
				} else {
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error In Addition.'));
					redirect_admin('banner');
				}
			}			
		}
		$this->outputData['categories'] = $this->category_model->getCategories(array('as_category.status'=>1,'as_category.category_type'=>1, 'as_category.category_level'=>2,'as_category.category_id !='=>242),'category_id,category_name,category_type','category_level',NULL,NULL,array('as_category.category_name','ASC'));
		$this->outputData['subcategories'] = $this->category_model->getCategories(array('as_category.status'=>1, 'as_category.parent_id !='=>0,'as_category.category_level'=>3,'as_category.category_id !='=>242 ),'category_id,category_name,parent_id','category_level',NULL,NULL,array('as_category.category_name','ASC'));
		$this->outputData['campaigns'] = $this->banner_model->getBannerCampaigns(array('as_banner_compaigns.status'=>1));
		$this->smarty->view('admin/banner/add.tpl', $this->outputData);
	}
	
	function edit(){
		$id = $this->uri->segment(3,0);
		$this->load->model('admin/category_model');
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit')) {

			$this->form_validation->set_rules('banner_type_id','Banner Type(Size)','required|trim|xss_clean');
			$this->form_validation->set_rules('campaign_id','Campaign','required|trim|xss_clean');
			$this->form_validation->set_rules('destination_url','Destination URL','required|trim|xss_clean');
			$this->form_validation->set_rules('status','','trim|xss_clean');
			if($this->form_validation->run()) {
				$updateData = array();
				$updateData['campaign_id']			= $this->input->post('campaign_id');
				$updateData['banner_type_id']		= $this->input->post('banner_type_id');
				$updateData['destination_url']		= $this->input->post('destination_url');
				$campaign_id                        = $this->input->post('campaign_id');
				$updateData['date_added'] 			= date('Y-m-d');
				$updateData['status'] 				= $this->input->post('status');
			    $valid_formats = array("jpg", "png", "gif", "bmp","jpeg","PNG","JPG","JPEG","GIF","BMP");
	            if($_FILES['banner_image_1']['name']!='') {               	
	                $ext = $this->getExtension($_FILES['banner_image_1']['name']);
	                if(in_array($ext,$valid_formats)) {
	                    $bucket= $this->config->item('bucket_name');
	                    $config = array(
	                        'access_key'     => $this->config->item('access_key'),
	                        'secret_key'     => $this->config->item('secret_key'),
	                        'use_ssl'        => false
	                    );
	                    $s3 = new S3($config);
	                    $actual_image_name = time().".".$ext;
	                    $tmp = $_FILES['banner_image_1']['tmp_name'];
	                    $s3->putObjectFile($tmp, $bucket,'banner/'.$campaign_id.'/'.$actual_image_name, S3::ACL_PUBLIC_READ);
	                    $updateData['banner_file'] = $actual_image_name;
	                }
	       		}
	       		if($this->input->post('category_type')==1){
	              	 $updateData['is_category'] = 0;
	            }else{
	            	$updateData['is_category'] = 1;
	            }
	            if($this->banner_model->updateBanners($id,$updateData)){
					$insertBannerDisplay = array();
					$insertBannerDisplay['banner_id'] = $id;						
					$banner_type_id		= $this->input->post('banner_type_id');
					if($banner_type_id==2 || $banner_type_id==3){
							if($this->input->post('category_type')==2){
								$this->db->query("DELETE FROM as_banner_category WHERE banner_id=$id");
								$subcategories_array=$this->input->post('sub_category_page');
								foreach($subcategories_array as $all_subcat){
									$insertBannerDisplay['category_id'] = $all_subcat;
									$this->banner_model->addBannerDisplayProductPage($insertBannerDisplay);

								}
							}
					} else if ($banner_type_id==4) {
						$this->db->query("DELETE FROM as_banner_display WHERE banner_id=$id");
						if($this->input->post('cart_page')==1){
							$insertBannerDisplay['display_id'] = 1;
								$this->banner_model->addBanner_displayPage($insertBannerDisplay);
						}
						if($this->input->post('allcategory_page')==2){
							$insertBannerDisplay['display_id'] = 2;
								$this->banner_model->addBanner_displayPage($insertBannerDisplay);
						}
					}
				}	
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Added.'));
				redirect_admin('banner');
			} else {
				$this->session->set_flashdata('msg',$this->common_model->admin_flash_message('error','Error In Addition.'));
				redirect_admin('banner');
			}				
		}
		$sql="SELECT banner_id,banner_type_id,campaign_id,banner_file,destination_url,is_category FROM `as_banner` WHERE  banner_id=$id";
      	$sql_1=$this->db->query($sql);
      	$rs	=$sql_1->row();

      	$sql_cat ="SELECT `as_banner_category`.`category_id`,parent_id FROM as_banner_category INNER JOIN as_category ON(`as_banner_category`.`category_id`=`as_category`.`category_id`) WHERE banner_id=$id";
      	$sql_cat1=$this->db->query($sql_cat)->result();
      	
      	$cat_array =array();
      	$parent_array = array();
      	foreach($sql_cat1 as $sub){
      		$cat_array[]=$sub->category_id;
      		$parent_array[]=$sub->parent_id;
      	}
      	$parent_array = array_unique($parent_array);
        $sql_display ="SELECT display_id FROM as_banner_display WHERE banner_id=$id";
      	$sql_cat2=$this->db->query($sql_display)->result();

      	$dis_array =array();
      	foreach($sql_cat2 as $display){
      		$dis_array[]=$display->display_id;
      	}
      	$this->outputData['category_select'] = $cat_array;
      	$this->outputData['parent_select'] = $parent_array;
        $this->outputData['display_select'] =$dis_array;
      	$this->outputData['banner_id'] =$id;
		$this->outputData['campaigns'] = $this->banner_model->getBannerCampaigns(array('as_banner_compaigns.status'=>1));	
        $this->outputData['categories'] = $this->category_model->getCategories(array('as_category.status'=>1,'as_category.category_type'=>1, 'as_category.category_level'=>2,'as_category.category_id !='=>242),'category_id,category_name,category_type','category_level',NULL,NULL,array('as_category.category_name','ASC'));
		$this->outputData['subcategories'] = $this->category_model->getCategories(array('as_category.status'=>1, 'as_category.parent_id !='=>0,'as_category.category_level'=>3,'as_category.category_id !='=>242 ),'category_id,category_name,parent_id','category_level',NULL,NULL,array('as_category.category_name','ASC'));
		$this->outputData['banner_detail'] = $sql_1;
		$this->smarty->view('admin/banner/banner_edit.tpl', $this->outputData);
	}

	function status(){
		$id = $this->uri->segment(2,0); 
		$conditions = array('as_banner.banner_id'=>$id);
		$fields = 'as_banner.status';
		$result = $this->banner_model->getBanners($conditions, $fields);
		$row = $result->row();
		
		$updateData = array();
		$updateData['status'] = ($row->status ==1 ? 0 : 1);
		if($this->banner_model->updateBanner($id, $updateData))
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Status Updated Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Updation! Please try again.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function delete()
	{
		$id = $this->uri->segment(4,0);
		$ids = array();
		$ids[] = $id;
		
		$this->banner_model->deleteBannerDisplayWebPage($id);
		$this->banner_model->deleteBannerDisplayProductPage($id);
		$this->banner_model->deleteBannerDisplayCityPage($id);
		$this->banner_model->deleteBannerDisplayOfferPage($id);
		$this->banner_model->deleteBannerDisplayUserCityPage($id);
		$this->banner_model->deleteBannerDisplay($id);
		
		$results = $this->banner_model->getBanners(array('as_banner.banner_id'=>$id));
		$res = $results->row();
		if($res->banner_file != '')
		{
			$config['upload_path'] = './uploaded_files/banner/';
			if (file_exists($config['upload_path'].$res->banner_file))
			{
				@unlink($config['upload_path'].$res->banner_file);
			}
		}
		
		if($this->banner_model->deleteBanner($id))
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Deleted Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Deletion! Please try again.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function batchProcess()
	{
		$updateData = array();
		if($this->input->post('Activate') || $this->input->post('Activate_x'))
		{
			$arr_banner_ids = $this->input->post('arr_banner_ids');
			$updateData['status'] = 1;
			if($this->banner_model->updateAll($arr_banner_ids, $updateData))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Activated Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Activation! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Deactivate') || $this->input->post('Deactivate_x'))
		{
			$arr_banner_ids = $this->input->post('arr_banner_ids');
			$updateData['status'] = 0;
			if($this->banner_model->updateAll($arr_banner_ids, $updateData))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) De-Activated Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in De-Activation! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Delete') || $this->input->post('Delete_x'))
		{
			$arr_banner_ids = $this->input->post('arr_banner_ids');
			for($i=0; $i<count($arr_banner_ids); $i++)
			{
				$this->banner_model->deleteBannerDisplayWebPage($arr_banner_ids[$i]);
				$this->banner_model->deleteBannerDisplayProductPage($arr_banner_ids[$i]);
				$this->banner_model->deleteBannerDisplayCityPage($arr_banner_ids[$i]);
				$this->banner_model->deleteBannerDisplayOfferPage($arr_banner_ids[$i]);
				$this->banner_model->deleteBannerDisplayUserCityPage($arr_banner_ids[$i]);
				$this->banner_model->deleteBannerDisplay($arr_banner_ids[$i]);
				
				$banners = $this->banner_model->getBanners(array('as_banner.banner_id'=>$arr_banner_ids[$i]));
				$banner = $banners->row();
				if($banner->banner_file != '')
				{
					$config['upload_path'] = './uploaded_files/banner/';
					if (file_exists($config['upload_path'].$banner->banner_file))
					{
						@unlink($config['upload_path'].$banner->banner_file);
					}
				}
			}
			
			if($this->banner_model->deleteAll($arr_banner_ids))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Deleted Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Deletion! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Save_order') || $this->input->post('Save_order_x'))
		{
			$sort_order = $this->input->post('sort_order'); 
			$banner_ids = $this->input->post('banner_ids');
			$updateData = array();
			for($i=0; $i<count($store_ids); $i++)
			{
				$updateData['sort_order'] = $sort_order[$i];
				$this->banner_model->updateBanner($banner_ids[$i], $updateData);
			}
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Order Saved Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function display_page()
	{
		$records = $this->banner_model->getBannerDisplayPages();
		
		$start = $this->uri->segment(4,0);
		$page_rows = $this->config->item('admin_listing_limit');
		$limit[0] = $page_rows;
		
		if($start > 0)
		{
			$limit[1]	= ($start-1) * $page_rows;
			$this->outputData['start'] = ($start-1) * $page_rows;
		} else {
			$limit[1]	= $start * $page_rows;
			$this->outputData['start'] = $start * $page_rows;
		}
		
		$order[0] = 'as_banner_page.banner_display_page_id';
		$order[1] = 'ASC';
		
		$this->outputData['display_pages'] = $this->banner_model->getBannerDisplayPages(NULL,NULL,NULL,$limit,$order);
		
		$this->load->library('pagination');
		$config['base_url'] = admin_url('banner/display_page');
		$config['total_rows'] = $records->num_rows(); 
		$config['per_page'] = $page_rows;
		$config['cur_page'] = $start;
		$this->outputData['total_pages'] = ceil($config['total_rows']/$this->config->item('admin_listing_limit'));
		$this->pagination->initialize($config);
		$this->outputData['pagination'] = $this->pagination->create_links2(false,'display_page');
		$this->smarty->view('admin/banner/display_page.tpl', $this->outputData);
	}
	
	function display_page_add()
	{ 
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit')) {
			$this->form_validation->set_rules('name','Name','required|trim|xss_clean');
			$this->form_validation->set_rules('php_file_name','PHP File Name','required|trim|xss_clean');		
			$this->form_validation->set_rules('sort_order','','trim|xss_clean');
			$this->form_validation->set_rules('status','','trim|xss_clean');
			
			if($this->form_validation->run()) {
				$insertData = array();
				$insertData['banner_display_page_id']	= '';
				$insertData['name']						= $this->input->post('name');
				$insertData['php_file_name']			= $this->input->post('php_file_name');
				$insertData['sort_order'] 				= $this->input->post('sort_order');
				$insertData['date_added'] 				= date('Y-m-d');
				$insertData['status'] 					= $this->input->post('status');
				
				$conditions = array('php_file_name'=>$this->input->post('php_file_name'));
				if($this->banner_model->checkBannerDisplayPage($conditions))
				{
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','This Category Name is already exists!'));
				}
				else
				{
					if($this->banner_model->addBannerDisplayPage($insertData))
					{
						$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Added.'));
						redirect_admin('banner/display_page');
					}
					else
					{
						$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error In Addition.'));
						redirect_admin('banner/display_page');
					}
				}
			}
		}
		$this->smarty->view('admin/banner/display_page_add.tpl');
	}
	
	function display_page_edit()
	{
		$id = $this->uri->segment(4,0);
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit')) {
			$this->form_validation->set_rules('name','Name','required|trim|xss_clean');
			$this->form_validation->set_rules('php_file_name','PHP File Name','required|trim|xss_clean');	
			$this->form_validation->set_rules('sort_order','','trim|xss_clean');
			$this->form_validation->set_rules('status','','trim|xss_clean');
			
			if($this->form_validation->run()) {
				$updateData = array();
				$updateData['name']				= $this->input->post('name');
				$updateData['php_file_name']	= $this->input->post('php_file_name');
				$updateData['sort_order'] 		= $this->input->post('sort_order');
				$updateData['date_modified'] 	= date('Y-m-d h:i:s');
				$updateData['status'] 			= $this->input->post('status');
				
				if($this->banner_model->updateBannerDisplayPage($id, $updateData))
				{
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Updated.'));
					redirect_admin('banner/display_page');
				}
				else
				{
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error In Updation.'));
					redirect_admin('banner/display_page');
				}
			}
		}
		$this->outputData['display_pages'] = $this->banner_model->getBannerDisplayPages(array('as_banner_page.banner_display_page_id'=>$id));
		$this->smarty->view('admin/banner/display_page_edit.tpl', $this->outputData);
	}
	
	function display_page_status()
	{
		$id = $this->uri->segment(4,0);
		$conditions = array('as_banner_page.banner_display_page_id'=>$id);
		$fields = 'as_banner_page.status';
		$result = $this->banner_model->getBannerDisplayPages($conditions, $fields);
		$row = $result->row();
		
		$updateData = array();
		$updateData['status'] = ($row->status ==1 ? 0 : 1);
		if($this->banner_model->updateBannerDisplayPage($id, $updateData))
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Status Updated Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Updation! Please try again.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function display_page_delete($id)
	{
		if($this->banner_model->deleteBannerDisplayPage($id))
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Deleted Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Deletion! Please try again.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function display_page_batchProcess()
	{
		$updateData = array();
		if($this->input->post('Activate') || $this->input->post('Activate_x'))
		{
			$arr_bannerids = $this->input->post('arr_bannerids');
			$updateData['status'] = 1;
			if($this->banner_model->updateBannerDisplayPageAll($arr_bannerids, $updateData))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Activated Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Activation! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Deactivate') || $this->input->post('Deactivate_x'))
		{
			$arr_bannerids = $this->input->post('arr_bannerids');
			$updateData['status'] = 0;
			if($this->banner_model->updateBannerDisplayPageAll($arr_bannerids, $updateData))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) De-Activated Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in De-Activation! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Delete') || $this->input->post('Delete_x'))
		{
			$arr_bannerids = $this->input->post('arr_bannerids');
			if($this->banner_model->deleteBannerDisplayPageAll($arr_bannerids))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Deleted Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Deletion! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Save_order') || $this->input->post('Save_order_x'))
		{
			$sort_order = $this->input->post('sort_order'); 
			$banner_ids = $this->input->post('banner_ids');
			$updateData = array();
			for($i=0; $i<count($banner_ids); $i++)
			{
				$updateData['sort_order'] = $sort_order[$i];
				$this->banner_model->updateBannerDisplayPage($banner_ids[$i], $updateData);
			}
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Order Saved Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function banner_type()
	{
		$records = $this->banner_model->getBannerTypes();
		
		$start = $this->uri->segment(4,0);
		$page_rows = $this->config->item('admin_listing_limit');
		$limit[0] = $page_rows;
		
		if($start > 0)
		{
			$limit[1]	= ($start-1) * $page_rows;
			$this->outputData['start'] = ($start-1) * $page_rows;
		} else {
			$limit[1]	= $start * $page_rows;
			$this->outputData['start'] = $start * $page_rows;
		}
		
		$order[0] = 'as_banner_type.sort_order';
		$order[1] = 'ASC';
		
		$this->outputData['banner_types'] = $this->banner_model->getBannerTypes(NULL,NULL,NULL,$limit,$order);
		
		$this->load->library('pagination');
		$config['base_url'] = admin_url('banner/banner_type');
		$config['total_rows'] = $records->num_rows(); 
		$config['per_page'] = $page_rows;
		$config['cur_page'] = $start;
		$this->outputData['total_pages'] = ceil($config['total_rows']/$this->config->item('admin_listing_limit'));
		$this->pagination->initialize($config);
		$this->outputData['pagination'] = $this->pagination->create_links2(false,'banner_type');
		$this->smarty->view('admin/banner/banner_type.tpl', $this->outputData);
	}
	
	function banner_type_add()
	{ 
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit')) {
			$this->form_validation->set_rules('name','Name','required|trim|xss_clean');
			$this->form_validation->set_rules('width','Banner Width','required|trim|xss_clean');
			$this->form_validation->set_rules('height','Banner Height','required|trim|xss_clean');
			$this->form_validation->set_rules('banner_type','Banner Type','required|trim|xss_clean');
			$this->form_validation->set_rules('banner_for[]','Banner For','required|trim|xss_clean');
			$this->form_validation->set_rules('sort_order','','trim|xss_clean');
			$this->form_validation->set_rules('status','','trim|xss_clean');
			$this->form_validation->set_rules('display_page_ids[]','Banner Display Page','required|trim|xss_clean');
			
			if($this->form_validation->run()) {
				$insertData = array();
				$insertData['banner_type_id']	= '';
				$insertData['name']				= $this->input->post('name');
				$insertData['width']			= $this->input->post('width');
				$insertData['height']			= $this->input->post('height');
				$insertData['banner_for']		= implode(',',$this->input->post('banner_for'));
				$insertData['banner_type']		= $this->input->post('banner_type');
				$insertData['sort_order'] 		= $this->input->post('sort_order');
				$insertData['date_added'] 		= date('Y-m-d');
				$insertData['status'] 			= $this->input->post('status');
				
				$conditions = array('name'=>$this->input->post('name'));
				if($this->banner_model->checkBannerType($conditions))
				{
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','This Category Name is already exists!'));
				}
				else
				{
					if($this->banner_model->addBannerType($insertData))
					{
						$banner_type_id = $this->db->insert_id();
						if($this->input->post('display_page_ids') != '')
						{
							foreach($this->input->post('display_page_ids') as $display_page_id)
							{
								$insertDisplayPage = array();
								$insertDisplayPage['banner_type_id']			= $banner_type_id;
								$insertDisplayPage['banner_display_page_id']	= $display_page_id;
								$this->banner_model->addBannerTypeDispalyPage($insertDisplayPage);
							}
						}
						$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Added.'));
						redirect_admin('banner/banner_type');
					}
					else
					{
						$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error In Addition.'));
						redirect_admin('banner/banner_type');
					}
				}
			}
		}
		$this->outputData['display_pages'] = $this->banner_model->getBannerDisplayPages(array('as_banner_page.status'=>1),'as_banner_page.name,as_banner_page.banner_display_page_id');
		$this->smarty->view('admin/banner/banner_type_add.tpl', $this->outputData);
	}
	
	function banner_type_edit()
	{
		$id = $this->uri->segment(4,0);
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit')) {
			$this->form_validation->set_rules('name','Name','required|trim|xss_clean');
			$this->form_validation->set_rules('width','Banner Width','required|trim|xss_clean');
			$this->form_validation->set_rules('height','Banner Height','required|trim|xss_clean');
			$this->form_validation->set_rules('banner_type','Banner Type','required|trim|xss_clean');
			$this->form_validation->set_rules('banner_for[]','Banner For','required|trim|xss_clean');
			$this->form_validation->set_rules('sort_order','','trim|xss_clean');
			$this->form_validation->set_rules('status','','trim|xss_clean');
			$this->form_validation->set_rules('display_page_ids[]','Banner Display Page','required|trim|xss_clean');
			
			if($this->form_validation->run()) {
				$updateData = array();
				$updateData['name']				= $this->input->post('name');
				$updateData['width']			= $this->input->post('width');
				$updateData['height']			= $this->input->post('height');
				$updateData['banner_type']		= $this->input->post('banner_type');
				$updateData['banner_for']		= implode(',',$this->input->post('banner_for'));
				$updateData['sort_order'] 		= $this->input->post('sort_order');
				$updateData['date_modified'] 	= date('Y-m-d h:i:s');
				$updateData['status'] 			= $this->input->post('status');
				
				if($this->banner_model->updateBannerType($id, $updateData))
				{
					if($this->input->post('display_page_ids') != '')
					{
						$this->banner_model->deleteBannerTypeDispalyPage($id);
						foreach($this->input->post('display_page_ids') as $display_page_id)
						{
							$insertDisplayPage = array();
							$insertDisplayPage['banner_type_id']			= $id;
							$insertDisplayPage['banner_display_page_id']	= $display_page_id;
							$this->banner_model->addBannerTypeDispalyPage($insertDisplayPage);
						}
					}
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Updated.'));
					redirect_admin('banner/banner_type');
				}
				else
				{
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error In Updation.'));
					redirect_admin('banner/banner_type');
				}
			}
		}
		$this->outputData['display_pages'] = $this->banner_model->getBannerDisplayPages(array('as_banner_page.status'=>1),'as_banner_page.name,as_banner_page.banner_display_page_id');
		$this->outputData['banner_type_display_pages'] = $this->banner_model->getBannerTypeDispalyPages(array('as_banner_type_display_page.banner_type_id'=>$id));
		$this->outputData['banner_types'] = $this->banner_model->getBannerTypes(array('as_banner_type.banner_type_id'=>$id));
		$this->smarty->view('admin/banner/banner_type_edit.tpl', $this->outputData);
	}
	
	function banner_type_status()
	{
		$id = $this->uri->segment(4,0);
		$conditions = array('as_banner_type.banner_type_id'=>$id);
		$fields = 'as_banner_type.status';
		$result = $this->banner_model->getBannerTypes($conditions, $fields);
		$row = $result->row();
		
		$updateData = array();
		$updateData['status'] = ($row->status ==1 ? 0 : 1);
		if($this->banner_model->updateBannerType($id, $updateData))
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Status Updated Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Updation! Please try again.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function banner_type_delete()
	{
		$id = $this->uri->segment(4,0);
		$this->banner_model->deleteBannerTypeDispalyPage($id);
		if($this->banner_model->deleteBannerType($id))
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Deleted Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Deletion! Please try again.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function banner_type_batchProcess()
	{
		$updateData = array();
		if($this->input->post('Activate') || $this->input->post('Activate_x'))
		{
			$arr_bannerids = $this->input->post('arr_bannerids');
			$updateData['status'] = 1;
			if($this->banner_model->updateBannerTypeAll($arr_bannerids, $updateData))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Activated Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Activation! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Deactivate') || $this->input->post('Deactivate_x'))
		{
			$arr_bannerids = $this->input->post('arr_bannerids');
			$updateData['status'] = 0;
			if($this->banner_model->updateBannerTypeAll($arr_bannerids, $updateData))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) De-Activated Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in De-Activation! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Delete') || $this->input->post('Delete_x'))
		{
			$arr_bannerids = $this->input->post('arr_bannerids');
			$this->banner_model->deleteBannerTypeDispalyPageAll($arr_bannerids);
			if($this->banner_model->deleteBannerTypeAll($arr_bannerids))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Deleted Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Deletion! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Save_order') || $this->input->post('Save_order_x'))
		{
			$sort_order = $this->input->post('sort_order'); 
			$banner_ids = $this->input->post('banner_ids');
			$updateData = array();
			for($i=0; $i<count($banner_ids); $i++)
			{
				$updateData['sort_order'] = $sort_order[$i];
				$this->banner_model->updateBannerType($banner_ids[$i], $updateData);
			}
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Order Saved Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function global_rate()
	{
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit'))
		{
			$this->form_validation->set_rules('ctr[]','','trim|xss_clean');
			$this->form_validation->set_rules('imp[]','','trim|xss_clean');
			if($this->form_validation->run())
			{
				$imp = $this->input->post('imp');
				if($this->input->post('ctr') != '')
				{
					foreach($this->input->post('ctr') as $key=>$ctr)
					{
						if($ctr!='' || $imp[$key]!='')
						{ 
							if($this->banner_model->checkGlobalRate(array('section'=>$key)))
							{
								$updateData = array();
								$updateData['click_through_rate'] 	= $ctr;
								$updateData['section'] 				= $key;
								$updateData['impressions_rate'] 	= $imp[$key];
								$updateData['date_modified'] 		= date('Y-m-d h:i:s');
								$this->banner_model->updateGlobalRate($key, $updateData);
							}
							else
							{ 
								$insertData = array();
								$insertData['global_rate_id']		= '';
								$insertData['section']				= $key;
								$insertData['click_through_rate'] 	= $ctr;
								$insertData['impressions_rate'] 	= $imp[$key];
								$insertData['date_added'] 			= date('Y-m-d');
								$this->banner_model->addGlobalRate($insertData);								
							}
						}
						else
						{
							$this->banner_model->deleteGlobalRate($key);
						}
					}
				}
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Submitted.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		$this->outputData['global_rates'] = $this->banner_model->getGlobalRates();
		$this->smarty->view('admin/banner/global_rate.tpl', $this->outputData);
	}
	
	function page_rate()
	{
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit'))
		{
			$this->form_validation->set_rules('webpages_ctr[]','','trim|xss_clean');
			$this->form_validation->set_rules('webpages_imp[]','','trim|xss_clean');
			if($this->form_validation->run())
			{
				$imp = $this->input->post('webpages_imp');
				if($this->input->post('webpages_ctr') != '')
				{
					foreach($this->input->post('webpages_ctr') as $key=>$ctr)
					{
						if($ctr!='' || $imp[$key]!='')
						{ 
							if($this->banner_model->checkPageRate(array('page_id'=>$key)))
							{
								$updateData = array();
								$updateData['click_through_rate'] 	= $ctr;
								$updateData['page_id'] 				= $key;
								$updateData['impressions_rate'] 	= $imp[$key];
								$updateData['date_modified'] 		= date('Y-m-d h:i:s');
								$this->banner_model->updatePageRate($key, $updateData);
							}
							else
							{ 
								$insertData = array();
								$insertData['webpage_rate_id']		= '';
								$insertData['page_id']				= $key;
								$insertData['click_through_rate'] 	= $ctr;
								$insertData['impressions_rate'] 	= $imp[$key];
								$insertData['date_added'] 			= date('Y-m-d');
								$this->banner_model->addPageRate($insertData);								
							}
						}
						else
						{
							$this->banner_model->deletePageRate($key);
						}
					}
				}
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Submitted.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		$this->outputData['global_rates'] = $this->banner_model->getGlobalRates();
		$this->outputData['page_rates'] = $this->banner_model->getPageRates();
		$this->outputData['pages'] = $this->page_model->getPages(array('as_cms.status'=>1,'as_cms.display_on'=>'aaramshop'),'page_id,title');
		$this->smarty->view('admin/banner/page_rate.tpl', $this->outputData);
	}
	
	function product_rate()
	{
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit'))
		{
			$this->form_validation->set_rules('cat_ctr[]','','trim|xss_clean');
			$this->form_validation->set_rules('cat_imp[]','','trim|xss_clean');
			if($this->form_validation->run())
			{
				$imp = $this->input->post('cat_imp');
				if($this->input->post('cat_ctr') != '')
				{ 
					foreach($this->input->post('cat_ctr') as $key=>$ctr)
					{
						$cat = explode('_',$key);
						if($ctr!='' || $imp[$key]!='')
						{
							if($this->banner_model->checkProductRate(array('subcategory_id'=>$cat[1])))
							{
								$updateData = array();
								$updateData['click_through_rate'] 	= $ctr;
								$updateData['impressions_rate'] 	= $imp[$key];
								$updateData['date_modified'] 		= date('Y-m-d h:i:s');
								$this->banner_model->updateProductRate($cat[1], $updateData);								
							}
							else
							{ 
								$insertData = array();
								$insertData['productpage_rate_id']	= '';
								$insertData['category_id']			= $cat[0];
								$insertData['subcategory_id']		= $cat[1];
								$insertData['click_through_rate'] 	= $ctr;
								$insertData['impressions_rate'] 	= $imp[$key];
								$insertData['date_added'] 			= date('Y-m-d');
								$this->banner_model->addProductRate($insertData);								
							}
						}
						else
						{
							$this->banner_model->deleteProductRate($cat[1]);
						}
					}
				}
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Submitted.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		$this->load->model('admin/category_model');
		$this->outputData['global_rates'] = $this->banner_model->getGlobalRates();
		$this->outputData['product_rates'] = $this->banner_model->getProductRates();
		$this->outputData['categories'] = $this->category_model->getCategories(array('as_category.status'=>1, 'as_category.parent_id'=>0),'category_id,name,parent_id',NULL,NULL,array('as_category.name','ASC'));
		$this->outputData['subcategories'] = $this->category_model->getCategories(array('as_category.status'=>1, 'as_category.parent_id !='=>0),'category_id,name,parent_id',NULL,NULL,array('as_category.name','ASC'));
		$this->smarty->view('admin/banner/product_rate.tpl', $this->outputData);
	}
	
	function locate_shop_rate()
	{
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit'))
		{
			$this->form_validation->set_rules('city_ctr[]','','trim|xss_clean');
			$this->form_validation->set_rules('city_imp[]','','trim|xss_clean');
			if($this->form_validation->run())
			{
				$imp = $this->input->post('city_imp');
				if($this->input->post('city_ctr') != '')
				{ 
					foreach($this->input->post('city_ctr') as $key=>$ctr)
					{
						$city = explode('_',$key);							
						if($ctr!='' || $imp[$key]!='')
						{
							if($this->banner_model->checkCityPageRate(array('city_id'=>$key)))
							{
								$updateData = array();
								$updateData['city_id']				= $key;
								$updateData['click_through_rate'] 	= $ctr;
								$updateData['impressions_rate'] 	= $imp[$key];
								$updateData['date_modified'] 		= date('Y-m-d h:i:s');
								$this->banner_model->updateCityPageRate($key, $updateData);
							}
							else
							{ 
								$insertData = array();
								$insertData['citypages_rate_id']	= '';
								$insertData['city_id']				= $key;
								$insertData['click_through_rate'] 	= $ctr;
								$insertData['impressions_rate'] 	= $imp[$key];
								$insertData['date_added'] 			= date('Y-m-d');
								$this->banner_model->addCityPageRate($insertData);								
							}
						}
						else
						{
							$this->banner_model->deleteCityPageRate($key);
						}
					}
				}
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Submitted.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		$this->load->model('admin/aaramshop_model');
		$this->outputData['global_rates'] = $this->banner_model->getGlobalRates();
		$this->outputData['city_rates'] = $this->banner_model->getCityPageRates();
		$this->outputData['cities'] = $this->aaramshop_model->getAaramshops(array('as_store.status'=>1),'as_store.city_id',NULL,NULL,NULL,array('as_store.city_id'));
		$this->smarty->view('admin/banner/locate_shop_rate.tpl', $this->outputData);
	}
	
	function offer_rate()
	{
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit'))
		{
			$this->form_validation->set_rules('offer_ctr[]','','trim|xss_clean');
			$this->form_validation->set_rules('offer_imp[]','','trim|xss_clean');
			if($this->form_validation->run())
			{
				$imp = $this->input->post('offer_imp');
				if($this->input->post('offer_ctr') != '')
				{ 
					foreach($this->input->post('offer_ctr') as $key=>$ctr)
					{
						$offer = explode('_',$key);							
						if($ctr!='' || $imp[$key]!='')
						{
							if($this->banner_model->checkOfferPageRate(array('offer_page_id'=>$key)))
							{
								$updateData = array();
								$updateData['offer_page_id']		= $key;
								$updateData['click_through_rate'] 	= $ctr;
								$updateData['impressions_rate'] 	= $imp[$key];
								$updateData['date_modified'] 		= date('Y-m-d h:i:s');
								$this->banner_model->updateOfferPageRate($key, $updateData);
							}
							else
							{ 
								$insertData = array();
								$insertData['offerpage_rate_id']	= '';
								$insertData['offer_page_id']		= $key;
								$insertData['click_through_rate'] 	= $ctr;
								$insertData['impressions_rate'] 	= $imp[$key];
								$insertData['date_added'] 			= date('Y-m-d');
								$this->banner_model->addOfferPageRate($insertData);								
							}
						}
						else
						{
							$this->banner_model->deleteOfferPageRate($key);
						}
					}
				}
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Submitted.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		$this->load->model('admin/city_model');
		$this->outputData['global_rates'] = $this->banner_model->getGlobalRates();
		$this->outputData['offer_rates'] = $this->banner_model->getOfferPageRates();
		$this->outputData['offer_pages'] = $this->banner_model->getOfferPages();
		$this->smarty->view('admin/banner/offer_rate.tpl', $this->outputData);
	}
	
	function campaign()
	{
		$records = $this->banner_model->getBannerCampaigns();
		
		$start = $this->uri->segment(4,0);
		$page_rows = $this->config->item('admin_listing_limit');
		$limit[0] = $page_rows;
		
		if($start > 0)
		{
			$limit[1]	= ($start-1) * $page_rows;
			$this->outputData['start'] = ($start-1) * $page_rows;
		} else {
			$limit[1]	= $start * $page_rows;
			$this->outputData['start'] = $start * $page_rows;
		}
		
		$order[0] = 'as_banner_compaigns.sort_order';
		$order[1] = 'ASC';
		
		$this->outputData['campaigns'] = $this->banner_model->getBannerCampaigns(NULL,NULL,NULL,$limit,$order);
		
		$this->load->library('pagination');
		$config['base_url'] = admin_url('banner/campaign');
		$config['total_rows'] = $records->num_rows(); 
		$config['per_page'] = $page_rows;
		$config['cur_page'] = $start;
		$this->outputData['total_pages'] = ceil($config['total_rows']/$this->config->item('admin_listing_limit'));
		$this->pagination->initialize($config);
		$this->outputData['pagination'] = $this->pagination->create_links2(false,'campaign');
		$this->smarty->view('admin/banner/campaign.tpl', $this->outputData);
	}
	
	function campaign_add()
	{ 
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit'))
		{
			$this->form_validation->set_rules('name','Campaign Name','required|trim|xss_clean');
			$this->form_validation->set_rules('start_date','Start Date','required|trim|xss_clean');
			$this->form_validation->set_rules('end_date','End Date','required|trim|xss_clean');
			$this->form_validation->set_rules('budget','Campaign Budget','required|trim|xss_clean');
			$this->form_validation->set_rules('brand_owner','','required|trim|xss_clean');
			//$this->form_validation->set_rules('brand_owner_id','','required|trim|xss_clean');
			$this->form_validation->set_rules('sort_order','','trim|xss_clean');
			$this->form_validation->set_rules('status','','trim|xss_clean');
			
			if($this->form_validation->run()) {
				$insertData = array();
				$insertData['campaign_id']	       = '';
				$insertData['campaign_name']	   = $this->input->post('name');
				$insertData['campaign_start_date'] = $this->input->post('start_date');
				$insertData['campaign_end_date']   = $this->input->post('end_date');
				$insertData['campaign_budget']	= $this->input->post('budget');
				$insertData['brand_owner']		= $this->input->post('brand_owner');
				//$insertData['brand_owner_id']	= $this->input->post('brand_owner_id');
				$insertData['sort_order'] 		= $this->input->post('sort_order');
				$insertData['date_added'] 		= date('Y-m-d');
				$insertData['status'] 			= $this->input->post('status');
				$conditions = array('campaign_name'=>$this->input->post('name'));
				if($this->banner_model->checkBannerCampaign($conditions))
				{
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','This Campaign Name is already exists!'));
				}
				else
				{
					if($this->banner_model->addBannerCampaign($insertData))
					{
					//create folder
						$campaign_id = $this->db->insert_id();
						$bucket= $this->config->item('bucket_name');
                        $config = array(
                            'access_key'     => $this->config->item('access_key'),
                            'secret_key'     => $this->config->item('secret_key'),
                            'use_ssl'        => false
                        );
                        $s3 = new S3($config);
                        $s3->putObject(array( 
						   'Bucket'       => $bucket, // Defines name of Bucket
						   'Key'          => "banner/".$campaign_id, //Defines Folder name
						   'Body'         => "",
						   'ACL'          => 'public-read' // Defines Permission to that folder
						));
                        //create folder end
				        $this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Added.'));
						redirect_admin('banner/campaign');
					}
					else
					{
						$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error In Addition.'));
						redirect_admin('banner/campaign');
					}
				}
			}
		}
		$this->smarty->view('admin/banner/campaign_add.tpl');
	}
	
	function campaign_edit()
	{
		$id = $this->uri->segment(3,0); 
		$this->form_validation->set_error_delimiters($this->config->item('field_error_start_tag'), $this->config->item('field_error_end_tag'));
		if($this->input->post('asubmit')) {
			$this->form_validation->set_rules('name','Campaign Name','required|trim|xss_clean');
			$this->form_validation->set_rules('start_date','Start Date','required|trim|xss_clean');
			$this->form_validation->set_rules('end_date','End Date','required|trim|xss_clean');
			$this->form_validation->set_rules('budget','Campaign Budget','required|trim|xss_clean');
			$this->form_validation->set_rules('sort_order','','trim|xss_clean');
			$this->form_validation->set_rules('status','','trim|xss_clean');
			
			if($this->form_validation->run()) {
				$updateData = array();
				$updateData['campaign_name']				= $this->input->post('name');
				$updateData['campaign_start_date']		    = $this->input->post('start_date');
				$updateData['campaign_end_date']			= $this->input->post('end_date');
				$updateData['campaign_budget']			    = $this->input->post('budget');
				$updateData['sort_order'] 		            = $this->input->post('sort_order');
				$updateData['date'] 	                    = date('Y-m-d h:i:s');
				$updateData['status'] 			            = $this->input->post('status');
				
				if($this->banner_model->updateBannerCampaign($id, $updateData))
				{
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Successfully Updated.'));
					redirect_admin('banner/campaign');
				}
				else 
				{
					$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error In Updation.'));
					redirect_admin('banner/campaign');
				}
			}
		}
		$this->outputData['campaigns'] = $this->banner_model->getBannerCampaigns(array('as_banner_compaigns.campaign_id'=>$id));
		$this->smarty->view('admin/banner/campaign_edit.tpl', $this->outputData);
	}
	
	function campaign_status()
	{
		$id = $this->uri->segment(3,0);
		$conditions = array('as_banner_compaigns.campaign_id'=>$id);
		$fields = 'as_banner_compaigns.status';
		$result = $this->banner_model->getBannerCampaigns($conditions, $fields);
		$row = $result->row();
		
		$updateData = array();
		$updateData['status'] = ($row->status ==1 ? 0 : 1);
		if($this->banner_model->updateBannerCampaign($id, $updateData))
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Status Updated Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Updation! Please try again.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function campaign_delete()
	{
		$id = $this->uri->segment(3,0);
		if($this->banner_model->deleteBannerCampaign($id))
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record Deleted Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
		else
		{
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Deletion! Please try again.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function campaign_batchProcess()
	{
		$updateData = array();
		if($this->input->post('Activate') || $this->input->post('Activate_x'))
		{
			$arr_campaignids = $this->input->post('arr_campaignids');
			$updateData['status'] = 1;
			if($this->banner_model->updateBannerCampaignAll($arr_campaignids, $updateData))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Activated Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Activation! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Deactivate') || $this->input->post('Deactivate_x'))
		{
			$arr_campaignids = $this->input->post('arr_campaignids');
			$updateData['status'] = 0;
			if($this->banner_model->updateBannerCampaignAll($arr_campaignids, $updateData))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) De-Activated Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in De-Activation! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Delete') || $this->input->post('Delete_x'))
		{
			$arr_campaignids = $this->input->post('arr_campaignids');
			if($this->banner_model->deleteBannerCampaignAll($arr_campaignids))
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Deleted Successfuly.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
			else
			{
				$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('error','Error in Deletion! Please try again.'));
				redirect($_SERVER['HTTP_REFERER']);
			}
		}
		elseif($this->input->post('Save_order') || $this->input->post('Save_order_x'))
		{
			$sort_order = $this->input->post('sort_order'); 
			$campaign_ids = $this->input->post('campaign_ids');
			$updateData = array();
			for($i=0; $i<count($campaign_ids); $i++)
			{
				$updateData['sort_order'] = $sort_order[$i];
				$this->banner_model->updateBannerCampaign($campaign_ids[$i], $updateData);
			}
			$this->session->set_flashdata('msg', $this->common_model->admin_flash_message('success','Record(s) Order Saved Successfuly.'));
			redirect($_SERVER['HTTP_REFERER']);
		}
	}
	
	function calculateBudget()
	{
		$output = '';
		$display_type = $this->input->post('display_type');
		$display_banner = $this->input->post('display_banner');
		$webpageSelected = $this->input->post('webpageSelected');
		$offerpageSelected = $this->input->post('offerpageSelected');
		$web_pages = explode(',',rtrim($this->input->post('web_pages'),','));
		$offer_pages = explode(',',rtrim($this->input->post('offer_pages'),','));
		$productPagesSelected = $this->input->post('productPagesSelected');
		$categorySelected = $this->input->post('categorySelected');
		$categoryId = explode(',',rtrim($this->input->post('categoryId'),','));
		$subcategorySelected = $this->input->post('subcategorySelected');
		$subcategoryId = explode(',',rtrim($this->input->post('subcategoryId'),','));
		$locateAnAaramShopSelected = $this->input->post('locateAnAaramShopSelected');
		$citySelected = $this->input->post('citySelected');
		$city_pages = explode(',',rtrim($this->input->post('city_pages'),','));
		$avg = '0.00';
		$offer_row = $page_row = $page_rate = $offer_rate = $city_rate = $city_row = $category_rate = $category_row = 0;
		if($display_banner == 'all_page')
		{
			$fields = 'AVG('.$display_type.') AS AVG';
			$results = $this->banner_model->getGlobalRates(NULL,$fields);
			if(isset($results) && $results->num_rows()>0)
			{
				foreach($results->result() as $res)
				{
					$avg = number_format($res->AVG,2);
				}
			}
		}
		else if($display_banner == 'particular_page')
		{
			if($webpageSelected == 1)
			{
				$results = $this->banner_model->getPageRates(NULL,$display_type,NULL,NULL,NULL,NULL,'page_id',$web_pages);
				if(isset($results) && $results->num_rows()>0)
				{
					foreach($results->result() as $res)
					{
						$page_rate = $page_rate+$res->$display_type;
					}
					$page_row = $results->num_rows();
				}				
			}
			if($offerpageSelected== 1)
			{
				$results = $this->banner_model->getOfferPageRates(NULL,$display_type,NULL,NULL,NULL,NULL,'offer_page_id',$offer_pages);
				if(isset($results) && $results->num_rows()>0)
				{
					foreach($results->result() as $res)
					{
						$offer_rate = $offer_rate+$res->$display_type;
					}
					$offer_row = $results->num_rows();
				}				
			}
			if($locateAnAaramShopSelected==1)
			{
				if($citySelected==1)
				{
					$results = $this->banner_model->getCityPageRates(NULL,$display_type);
					if(isset($results) && $results->num_rows()>0)
					{
						foreach($results->result() as $res)
						{
							$city_rate = $city_rate+$res->$display_type;
						}
						$city_row = $results->num_rows();
					}
				}
				else if($citySelected==2)
				{
					$results = $this->banner_model->getCityPageRates(NULL,$display_type,NULL,NULL,NULL,NULL,'city_id',$city_pages);
					if(isset($results) && $results->num_rows()>0)
					{
						foreach($results->result() as $res)
						{
							$city_rate = $city_rate+$res->$display_type;
						}
						$city_row = $results->num_rows();
					}
				}
			}
			if($productPagesSelected==1)
			{
				if($categorySelected==1)
				{
					$results = $this->banner_model->getProductRates(NULL,$display_type);
					if(isset($results) && $results->num_rows()>0)
					{
						foreach($results->result() as $res)
						{
							$category_rate = $category_rate+$res->$display_type;
						}
						$category_row = $results->num_rows();
					}
				}
				else if($categorySelected==2)
				{
					$results = $this->banner_model->getProductRates(NULL,$display_type,NULL,NULL,NULL,NULL,'subcategory_id',$subcategoryId);
					if(isset($results) && $results->num_rows()>0)
					{
						foreach($results->result() as $res)
						{
							$category_rate = $category_rate+$res->$display_type;
						}
						$category_row = $results->num_rows();
					}
				}
			}
			$sum = $page_rate+$offer_rate+$city_rate+$category_rate;
			$total_record = $page_row + $offer_row + $city_row+$category_row;
			if($total_record>0)
			{
				$avg = 	number_format($sum/$total_record,2);
			}			
		}
		
		if($display_type == 'click_through_rate')
		{
			$output .= 'Min. suggested click through rate :$$'.$avg.'$$ per click';
		}
		else
		{
			$output .= 'Min. suggested cost per 1000 imp. : $$'.$avg.'$$';
		}
		echo $output;
	}
	
	// function _image_check(){
	// 	if(isset($_FILES) and $_FILES['banner_image_1']['name']=='')
	// 	{
	// 		$this->form_validation->set_message('_image_check', 'The Upload Image field is required.');
	// 		return false;
	// 	}
	// 	else
	// 	{
	// 		$records = $this->banner_model->getBannerTypes(array('as_banner_type.banner_type_id'=>$_POST['banner_type_id']),'as_banner_type.width, as_banner_type.height');
	// 		if(isset($records) && $records->num_rows()>0)
	// 		{
	// 			$record = $records->row();
	// 			list($width, $height, $type, $attr) = getimagesize($_FILES['banner_image_1']['tmp_name']);
	// 			if($width==$record->width && $height==$record->height)
	// 			{
	// 				return true;
	// 			}
	// 			else
	// 			{
	// 				$this->form_validation->set_message('_image_check', 'The Upload Image size not match with Banner Type.');
	// 				return false;
	// 			}
	// 		}
	// 		return true;
	// 	}
	// }
	
	// function _flash_check(){
	// 	if(isset($_FILES) and $_FILES['banner_file']['name']=='')
	// 	{
	// 		$this->form_validation->set_message('_flash_check', 'The Upload Banner field is required.');
	// 		return false;
	// 	}
	// 	else
	// 	{
	// 		$records = $this->banner_model->getBannerTypes(array('as_banner_type.banner_type_id'=>$_POST['banner_type_id']),'as_banner_type.width, as_banner_type.height');
	// 		if(isset($records) && $records->num_rows()>0)
	// 		{
	// 			$record = $records->row();
	// 			list($width, $height, $type, $attr) = getimagesize($_FILES['banner_file']['tmp_name']);
	// 			if($width==$record->width && $height==$record->height)
	// 			{
	// 				return true;
	// 			}
	// 			else
	// 			{
	// 				$this->form_validation->set_message('_flash_check', 'The Upload Banner size not match with Banner Type.');
	// 				return false;
	// 			}
	// 		}
	// 		return true;
	// 	}
	// }
	
	function autocomplete_campaign()
	{
		$json = array();		
		$campaignName = $this->input->post('filter_campaign');
		$results = $this->banner_model->getBanners(NULL,'as_banner_compaigns.campaign_id,as_banner_compaigns.name',array('as_banner_compaigns.name'=>$campaignName),NULL,NULL,array('as_banner_compaigns.campaign_id'));
		if(isset($results) && $results->num_rows()>0)
		{
			foreach ($results->result() as $result)
			{
				$json[] = array(
					'campaign_id'	=> $result->campaign_id,
					'name'    		=> $result->name,
				);
			}
		}
		
		return $this->output->set_content_type('application/json')->set_output(json_encode($json)); exit;
	}
	//Function
    function getExtension($str) {
         $i = strrpos($str,".");
         if (!$i) { return ""; } 

         $l = strlen($str) - $i;
         $ext = substr($str,$i+1,$l);
         return $ext;
    } 
}