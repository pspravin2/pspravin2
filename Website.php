<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Website extends CI_Controller {
	public $selected_city;
	public $selected_location;
	public $locations_filter;
	public $dynamic_footer;
	public function __construct()
	{
		parent::__construct();
		$this->load->model('Common_m');
		$this->load->helper('form');

		$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		if (!isset($_SESSION['HTTP_REFERER'])) {
			$_SESSION['HTTP_REFERER'] = $ref;
		}

		if (isset($_SESSION['HTTP_REFERER'])) {
			if ($_SESSION['HTTP_REFERER']!=$ref && strpos($ref, 'kalpataru') === false && $ref!="") {
				$_SESSION['HTTP_REFERER'] = $ref;
			}
		}

		if (isset($_GET['utm_source'])) {
			$_SESSION['HTTP_REFERER'] = $_GET['utm_source'];
		}
		if (isset($_GET['utm_medium'])) {
			$_SESSION['UTM_MEDIUM'] = $_GET['utm_medium'];
		}
		if (isset($_GET['utm_campaign'])) {
			$_SESSION['UTM_CAMPAIGN'] = $_GET['utm_campaign'];
		}

		// echo $_COOKIE['HTTP_REFERER'];
		$city = isset($_GET['city']) ? $_GET['city'] : "";
		if ($city!="") {
			$this->db->select('*');
			$this->db->from('cities');
			$this->db->where("id='$city'");
			$query1 = $this->db->get(); 
			$city_result = $query1->result();
			$this->selected_city = isset($city_result[0]->title) ? $city_result[0]->title : "";
		}
		else
		{
			$this->selected_city = "";
		}

		$this->db->select('*');
		$this->db->from('locations');
		$this->db->where("city_id = '$city'");
		$query_locations = $this->db->get(); 
		$this->locations_filter = $query_locations->result();

		$location = isset($_GET['location']) ? $_GET['location'] : "";
		if ($location!="") {
			$this->db->select('*');
			$this->db->from('locations');
			$this->db->where("id='$location'");
			$query_location = $this->db->get(); 
			$location_result = $query_location->result();
			$this->selected_location = isset($location_result[0]->location) ? $location_result[0]->location : "";
		}
		else
		{
			$this->selected_location = "";
		}

		//Dynamic footer
		$this->dynamic_footer = $this->Common_m->getFooter();
		/*echo '<pre>';
		print_r($this->dynamic_footer);
		echo '</pre>';die;*/
	}

	//New listing page functionality
	public function projects_listing($param1=NULL,$param2=NULL){
	    
	  
		$data = [];
		$whereArr = [];
		$likeArr = [];
		$forProjectInner = false;
		
	    $this->db->select('*');
		$this->db->from('properties');
		$this->db->join('rera_details', 'properties.id = rera_details.property_id', 'left');
	  
	   	$query2 = $this->db->get(); 
        $data2 = $query2->result();
    
	
		if($param1 != "" && $param2 == ""){
			//City filter
			$param1Arr = explode('projects-in-', $param1);
			$city_name = end($param1Arr);
			$whereArr['cities.slug'] = $city_name;

			$result = $this->getProjectsFor($city_name,$whereArr,$likeArr);
			if(!empty($result) && $result['status'] == true){
				$data = $result['data'];
				/*echo '<pre>';
				print_r($data);
				echo '</pre>';die;*/
				$this->load->view('website/projects',$data);
			}else{
				$this->load->view('website/err404');
			}
		}
		elseif($param1 != "" && $param2 != ""){
			//City and Location filter or City, Location and Typology filter or show Inner page
			$city_name = $param1;
			$param2Arr = explode('projects-in-', $param2);
			if(isset($param2Arr[0]) && empty($param2Arr[0])){ //For location
				$location_name = end($param2Arr);
				$whereArr['cities.slug'] = $city_name;
				$whereArr['locations.slug'] = $location_name;
				$result = $this->getProjectsFor($city_name,$whereArr,$likeArr);
			}
			elseif(isset($param2Arr[0]) && !empty($param2Arr[0])){ //For Typology Or Project Inner page
				$newParam2Arr = explode('-flats-in-', $param2);
				// $typology = end($newParam2Arr);
				$typology = current($newParam2Arr);
				$location_name = end($newParam2Arr);

				//Check if 2nd param is project slug
				$this->db->where('slug',$typology);
				$ifProperty = $this->db->get('properties')->row();

				if(!empty($ifProperty)){
					$forProjectInner = true;
					$whereArr['cities.slug'] = $city_name;
					$whereArr['properties.slug'] = $typology;
					$result = $this->getProjectsFor($city_name,$whereArr,$likeArr,'project_inner');
					if(!empty($result) && $result['status'] == true){
						$data = $result['data'];
						 $data['datas'] = $data2;
						
						$this->load->view('website/project-inner',$data);
					}else{
						$this->load->view('website/err404');
					}
				}else{
					// $typologyArr = preg_split('#(?<=\d)(?=[a-z])#i', $typology);
					$typologyArr = implode(' ', explode('-', $typology));
					/*if(is_array($typologyArr)){
						foreach ($typologyArr as $tKey => $tVal) {
							array_push($likeArr, ['typology_for_footer'=>$tVal]);
						}
					}*/

					$whereArr['cities.slug'] = $city_name;
					$whereArr["FIND_IN_SET('".$typologyArr."', typology_for_footer) <> "] = 0;
					if(isset($location_name) && !empty($location_name)){
						$whereArr['locations.slug'] = $location_name;
					}
					$result = $this->getProjectsFor($city_name,$whereArr,$likeArr);
					// print_r($result);die;
				}
			}

			if($forProjectInner == false){
				if(!empty($result) && $result['status'] == true){
					$data = $result['data'];
					 $data['datas'] = $data2;
					$this->load->view('website/projects',$data);
				}else{
					$this->load->view('website/err404');
				}
			}
		}else{
			$this->load->view('website/err404');
		}
	}

	public function completedNewProject()
	{
		$data = array();
		$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties');
		$this->db->join('cities', 'cities.id = properties.city', 'left');
		$this->db->join('locations', 'locations.id = properties.location', 'left');
		$this->db->where("active=1 AND completed = '1'");
		$this->db->order_by('sort_order');
		$query1 = $this->db->get(); 
		$properties = $query1->result();
		$data['properties'] = $properties;
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/completed-newprojects',$data);
	}
	public function getProjectsFor($city_name,$whereArr,$likeArr,$pageType=NULL){
		$data = [];
		$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties');
		$this->db->join('cities', 'cities.id = properties.city', 'left');
		$this->db->join('locations', 'locations.id = properties.location', 'left');
		$this->db->order_by('sort_order');
		if(!empty($whereArr)){
			$this->db->where($whereArr);
		}
		if(!empty($likeArr)){
			foreach($likeArr as $key=>$value){
				$this->db->like($value);
			}
		}
		if($pageType == "project_inner"){
			$result = $this->db->get()->row();
			if(!empty($result)){
				$data['properties'] = $properties = $result;
				$data['dynamic_footer'] = $this->Common_m->getFooter($city_name,$properties->slug,'project-inner');
				$am = isset($properties->overview_amenity) ? $properties->overview_amenity : "'noamenity'";
				if ($am == "") {
					$data['amenities'] = array();
				}	
				else{
					$amenities = $this->Common_m->getAllDataWithCondition("amenities","id IN ($am)");
					$data['amenities'] = $amenities;
				}
				return ['status' => true, 'data' => $data];
			}
			else{
				return ['status'=>false];
			}
		}else{
			$result = $this->db->get()->result();
			// return $this->db->last_query();
			if(!empty($result)){
				$data['dynamic_footer'] = $this->Common_m->getFooter($city_name);
				$data['top_picks'] = array();
				$data['on_going'] = array();
				foreach ($result as $key => $value) {
					$value->google_pixel = "";
					$value->facebook_pixel = "";
					if ($value->top_picks==1) {
						array_push($data['top_picks'], $value);
					}
					if($value->on_going==1){
						array_push($data['on_going'], $value);
					}
					if ($value->completed==1) {
						array_push($data['top_picks'], $value);
					}
				}
				return ['status' => true, 'data' => $data];
			}else{
				return ['status'=>false];
			}
		}
	}

	/* listing page summit sample page static */
	public function projects_listing_one($param1=NULL,$param2=NULL){
		$data = [];
		$whereArr = [];
		$likeArr = [];
		$forProjectInner = false;
		if($param1 != "" && $param2 == ""){
			//City filter
			$param1Arr = explode('projects-in-', $param1);
			$city_name = end($param1Arr);
			$whereArr['cities.slug'] = $city_name;

			$result = $this->getProjectsForOne($city_name,$whereArr,$likeArr);
			if(!empty($result) && $result['status'] == true){
				$data = $result['data'];
				/*echo '<pre>';
				print_r($data);
				echo '</pre>';die;*/
				$this->load->view('website/projects-one',$data);
			}else{
				$this->load->view('website/err404');
			}
		}
		elseif($param1 != "" && $param2 != ""){
			//City and Location filter or City, Location and Typology filter or show Inner page
			$city_name = $param1;
			$param2Arr = explode('projects-in-', $param2);
			if(isset($param2Arr[0]) && empty($param2Arr[0])){ //For location
				$location_name = end($param2Arr);
				$whereArr['cities.slug'] = $city_name;
				$whereArr['locations.slug'] = $location_name;
				$result = $this->getProjectsFor($city_name,$whereArr,$likeArr);
			}
			elseif(isset($param2Arr[0]) && !empty($param2Arr[0])){ //For Typology Or Project Inner page
				$newParam2Arr = explode('-flats-in-', $param2);
				// $typology = end($newParam2Arr);
				$typology = current($newParam2Arr);
				$location_name = end($newParam2Arr);

				//Check if 2nd param is project slug
				$this->db->where('slug',$typology);
				$ifProperty = $this->db->get('properties')->row();

				if(!empty($ifProperty)){
					$forProjectInner = true;
					$whereArr['cities.slug'] = $city_name;
					$whereArr['properties.slug'] = $typology;
					$result = $this->getProjectsForOne($city_name,$whereArr,$likeArr,'project_inner');
					if(!empty($result) && $result['status'] == true){
						$data = $result['data'];
						print_r($data);
						$this->load->view('website/project-inner-one',$data);
					}else{
						$this->load->view('website/err404');
					}
				}else{
					// $typologyArr = preg_split('#(?<=\d)(?=[a-z])#i', $typology);
					$typologyArr = implode(' ', explode('-', $typology));
					/*if(is_array($typologyArr)){
						foreach ($typologyArr as $tKey => $tVal) {
							array_push($likeArr, ['typology_for_footer'=>$tVal]);
						}
					}*/

					$whereArr['cities.slug'] = $city_name;
					$whereArr["FIND_IN_SET('".$typologyArr."', typology_for_footer) <> "] = 0;
					if(isset($location_name) && !empty($location_name)){
						$whereArr['locations.slug'] = $location_name;
					}
					$result = $this->getProjectsForOne($city_name,$whereArr,$likeArr);
					// print_r($result);die;
				}
			}

			if($forProjectInner == false){
				if(!empty($result) && $result['status'] == true){
					$data = $result['data'];
					$this->load->view('website/projects-one',$data);
				}else{
					$this->load->view('website/err404');
				}
			}
		}else{
			$this->load->view('website/err404');
		}
	}

	public function getProjectsForOne($city_name,$whereArr,$likeArr,$pageType=NULL){
		$data = [];
		$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties');
		$this->db->join('cities', 'cities.id = properties.city', 'left');
		$this->db->join('locations', 'locations.id = properties.location', 'left');
		$this->db->order_by('sort_order');
		if(!empty($whereArr)){
			$this->db->where($whereArr);
		}
		if(!empty($likeArr)){
			foreach($likeArr as $key=>$value){
				$this->db->like($value);
			}
		}
		if($pageType == "project_inner"){
			$result = $this->db->get()->row();
			if(!empty($result)){
				$data['properties'] = $properties = $result;
				$data['dynamic_footer'] = $this->Common_m->getFooter($city_name,$properties->slug,'project-inner');
				$am = isset($properties->overview_amenity) ? $properties->overview_amenity : "'noamenity'";
				if ($am == "") {
					$data['amenities'] = array();
				}	
				else{
					$amenities = $this->Common_m->getAllDataWithCondition("amenities","id IN ($am)");
					$data['amenities'] = $amenities;
				}
				return ['status' => true, 'data' => $data];
			}
			else{
				return ['status'=>false];
			}
		}else{
			$result = $this->db->get()->result();
			// return $this->db->last_query();
			if(!empty($result)){
				$data['dynamic_footer'] = $this->Common_m->getFooter($city_name);
				$data['top_picks'] = array();
				$data['on_going'] = array();
				foreach ($result as $key => $value) {
					$value->google_pixel = "";
					$value->facebook_pixel = "";
					if ($value->top_picks==1) {
						array_push($data['top_picks'], $value);
					}
					if($value->on_going==1){
						array_push($data['on_going'], $value);
					}
					if ($value->completed==1) {
						array_push($data['top_picks'], $value);
					}
				}
				return ['status' => true, 'data' => $data];
			}else{
				return ['status'=>false];
			}
		}
	}

	
	public function index()
	{
		$data = array();
		$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties');
		$this->db->join('cities', 'cities.id = properties.city', 'left');
		$this->db->join('locations', 'locations.id = properties.location', 'left');
		$this->db->where('active=1 AND slider=1');
		$this->db->order_by("slider_order", "asc");
		$query1 = $this->db->get(); 
		$properties = $query1->result();
		$data['slider'] = array();
		$data['banner_type'] = array();
		$data['cities'] = $this->Common_m->getAllData("cities");
		foreach ($properties as $key => $value) {

			$value->google_pixel = "";
			$value->facebook_pixel = "";

			if ($value->slider==1) {
				array_push($data['slider'], $value);
				array_push($data['banner_type'], $value->banner_type);
			}
		}



		$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties');
		$this->db->join('cities', 'cities.id = properties.city', 'left');
		$this->db->join('locations', 'locations.id = properties.location', 'left');
		$this->db->where('active=1 AND featured=1');
		$this->db->order_by("featured_order", "asc");
		$query1 = $this->db->get(); 
		$properties = $query1->result();
		$data['featured'] = array();
		foreach ($properties as $key => $value) {

			$value->google_pixel = "";
			$value->facebook_pixel = "";
			if($value->featured==1)
			{
				array_push($data['featured'], $value);
			}
		}

		//Dynamic footer
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/index',$data);
	}
	public function getCities(){
		$data = file_get_contents('php://input');
		$json = json_decode($data, true);
		$property_type = $json['property_type'];
		$data = array();
		$query = "SELECT DISTINCT cities.* FROM cities LEFT JOIN properties ON properties.city=cities.id WHERE properties.property_type='".$property_type."' ORDER BY cities.id";
		$result = $this->db->query($query)->result();
		$data['cities'] = $result;
		print_r(json_encode($data));
	}
	public function getLocations()
	{
		$data = file_get_contents('php://input');
		$json = json_decode($data, true);
		$city_id = $json['city_id'];
		$property_type = $json['property_type'];
		$data = array();
		$query = "SELECT DISTINCT locations.* FROM locations LEFT JOIN properties ON properties.location=locations.id WHERE properties.property_type='".$property_type."' AND locations.city_id='".$city_id."'";
		$result = $this->db->query($query)->result();
		$data['locations'] = $result;
		print_r(json_encode($data));
	}
	public function listingPage()
	{
		$data = array();
		
		//Dynamic footer
		$data['dynamic_footer'] = $this->dynamic_footer;

		$page_url=current_url();
		$page_url_arr = explode("/", $page_url);
		$property_type = array_pop($page_url_arr);

		$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties');
		$this->db->join('cities', 'cities.id = properties.city', 'left');
		$this->db->join('locations', 'locations.id = properties.location', 'left');
		$this->db->where("active=1 AND property_type LIKE '%$property_type%'");
		$this->db->order_by('sort_order');
		$query1 = $this->db->get(); 
		$properties = $query1->result();
		if(!empty($properties)){
			$data['top_picks'] = array();
			$data['on_going'] = array();
			$data['property_type'] = $property_type;
			foreach ($properties as $key => $value) {

				$value->google_pixel = "";
				$value->facebook_pixel = "";

				if ($value->top_picks==1) {
					array_push($data['top_picks'], $value);
				}
				if($value->on_going==1)
				{
					array_push($data['on_going'], $value);
				}
			}
			$this->load->view('website/listing-page',$data);
		}else{
			$data = array();
			$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
			$this->db->from('properties');
			$this->db->join('cities', 'cities.id = properties.city', 'left');
			$this->db->join('locations', 'locations.id = properties.location', 'left');
			$this->db->where("active=1");
			$this->db->order_by('sort_order');
			$query1 = $this->db->get(); 
			$properties = $query1->result();
			$data['top_picks'] = array();
			$data['on_going'] = array();
			foreach ($properties as $key => $value) {
				$value->google_pixel = "";
				$value->facebook_pixel = "";
				if ($value->top_picks==1) {
					array_push($data['top_picks'], $value);
				}
				if($value->on_going==1)
				{
					array_push($data['on_going'], $value);
				}
			}
			$this->load->view('website/projects',$data);
		}
	}
	public function projectInner()
	{
		$data = array();
		$page_url=current_url();
		$page_url_arr = explode("/", $page_url);
		$last_val = array_pop($page_url_arr);

		$this->db->select('properties.*,cities.title as city_name,locations.location as location_name');
		$this->db->from('properties');
		$this->db->join('cities', 'cities.id = properties.city', 'left');
		$this->db->join('locations', 'properties.location = locations.id', 'left');
	
		if(is_numeric($last_val)){
			$this->db->where('properties.id', $last_val);
		}else{
			$this->db->where('properties.slug', $last_val);
		}
		$query1 = $this->db->get(); 
		$properties = $query1->row();
		if(!empty($properties)){
			$data['properties'] = $query1->result()[0];
			$am = isset($properties->overview_amenity) ? $properties->overview_amenity : "'noamenity'";
			if ($am == "") {
				$data['amenities'] = array();
			}	
			else
			{
				$amenities = $this->Common_m->getAllDataWithCondition("amenities","id IN ($am)");
				$data['amenities'] = $amenities;
			}
			$data['dynamic_footer'] = $this->Common_m->getFooter($data['properties']->city_name);
			$this->load->view('website/project-inner',$data);
		}else{
			$this->load->view('website/err404');
		}
	}
	public function getDataOnScroll()
	{
		$data1 = array();
		$data = file_get_contents('php://input');
		$json = json_decode($data, true);
		if(!is_array($json) && empty($json)){
			redirect(base_url());exit(4);
		}
		$id = $json['id'];
		$properties = $this->Common_m->getAllDataWithCondition("properties","id='$id'");
		

		$am = isset($properties[0]->amenities) ? $properties[0]->amenities : "'noamenity'";
		// $amenities = $this->Common_m->getAllDataWithCondition("amenities","id IN ($am)");
		if ($am == "") {
			$amenities = array();
		}	
		else
		{

			
 
$dataam = explode(",",$am);
for($i=0;$i<count($dataam);$i++)
{
    $idam = $dataam[$i];


$propertiesamen[] = $this->Common_m->getAllDataWithCondition("amenities","id='$idam'");

}


for($i=0;$i<count($propertiesamen);$i++)
{
   for($j=0;$j<1;$j++)
{
$propertiesamen2[] = $propertiesamen[$i][$j];
}
}

		//	$amenities = $this->Common_m->getAllDataWithCondition("amenities","id IN ($am)");
//$qrty = "select * from amenities where id IN ($am)";


			$amenities = $propertiesamen2 ;
		}

		$pr = isset($properties[0]->projects) ? $properties[0]->projects : "'noproperty'";
		$data1['amenities'] = $amenities;
		//$construction_details = $this->Common_m->getAllDataWithConditionDesc("construction_details","property_id ='$id'");
		$construction_details = $this->Common_m->getAllDataWithConditionDescconst("construction_details","property_id ='$id'");
		$data1['construction_details'] = $construction_details;
		if ($pr!="") {
			$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
			$this->db->from('properties');
			$this->db->join('cities', 'cities.id = properties.city', 'left');
			$this->db->join('locations', 'properties.location = locations.id', 'left');
			$this->db->where("properties.id IN (".$pr.")",NULL, false);
			//$this->db->order_by('sort_order');
			$query1 = $this->db->get(); 
			$res = $query1->result(); 
			foreach ($res as $key => $value) {
				$seo_title = $this->Common_m->seoUrl($value->title);
				//$value->rel_link = base_url().'projects/'.$value->property_type.'/'.$seo_title.'/'.$value->id;
				$value->rel_link = base_url().$value->city_slug.'/'.$value->slug;
			}
			$data1['property_details'] = $res;
		}
		else
		{
			$data1['property_details'] = array();
		}
		print_r(json_encode($data1));
	}
	public function cleanInput($input) {
	    $search = array(
	        '@<script[^>]*?>.*?</script>@si',   // Strip out javascript
	        '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
	        '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
	        '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments
	    );

	    $output = preg_replace($search, '', $input);

	    $search1 = array(
	    	'/[^0-9a-zA-Z_ ]/'
	    );
	    $output = preg_replace($search1, '', $output);

	    return $output;
	}
	public function searchResult()
	{
	   // error_reporting(0);

		$data = array();
		// $page_url=current_url();
		// $page_url_arr = explode("/", $page_url);
		// $property_type = array_pop($page_url_arr);
		// foreach ($_GET as $key => $value) {
		// 	$_GET[$key] = $this->cleanInput($value);
		// }
		foreach ($_GET as $key => $value) {
			$_GET[$key] = $value;
		}
		$category = $_GET['category'];
		$city = $_GET['city'];
		$location = $_GET['location'];
        $search_box = $_GET['search_box'];

        $typology = str_replace('/[ -]+/', ',', trim($_GET['typology']));

		$filter = "";

	
        if ($search_box!=="")
        {
            $filter .=" AND properties.completed= 0 AND properties.title LIKE '%$search_box%' OR properties.typology_for_footer LIKE '%$search_box%'";
        } 

		if ($category!="") {
			$filter .="AND properties.completed= 0 AND property_type LIKE '%$category%'";
		}
		if ($city!="") {
			$filter .="AND properties.completed= 0 AND properties.city='$city'";
		}
		if ($location!="") {
			$filter .="AND properties.completed= 0 AND properties.location = '$location'";
		}
		if ($typology!="") {
			// $filter +=" AND typology FIND_IN_SET ($typology)";
			/*$typ = explode(",", $typology);*/
            $typ = str_split($typology);
			$sql = array(); 
			foreach($typ as $word){
				$sql[] = "typology LIKE '%$word%'";
			}
			$filter .= ' AND ('.implode(" OR ", $sql).')';
		}
		$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties');
		$this->db->join('cities', 'cities.id = properties.city', 'left');
		$this->db->join('locations', 'locations.id = properties.location', 'left');
		$this->db->where("active=1 $filter");
		$this->db->order_by('sort_order');
		$query1 = $this->db->get(); 
        

		$properties = $query1->result();

			$data['properties'] = $properties;
		if ($city!="") {
			$cityResult = $this->db->where('id',$city)->get('cities')->row();
			if(!empty($cityResult)){
				$data['dynamic_footer'] = $this->Common_m->getFooter($cityResult->title);
			}else{
				$data['dynamic_footer'] = $this->dynamic_footer;
			}
		}else{
			$data['dynamic_footer'] = $this->dynamic_footer;
		}
		$this->load->view('website/search-results',$data);
	}
	public function projects()
	{
		$data = array();
		$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties');
		$this->db->join('cities', 'cities.id = properties.city', 'left');
		$this->db->join('locations', 'locations.id = properties.location', 'left');
		$this->db->where("active=1");
		$this->db->order_by('sort_order');
		$query1 = $this->db->get(); 
		$properties = $query1->result();
		$data['top_picks'] = array();
		$data['on_going'] = array();
		foreach ($properties as $key => $value) {
			$value->google_pixel = "";
			$value->facebook_pixel = "";
			if ($value->top_picks==1) {
				array_push($data['top_picks'], $value);
			}
			if($value->on_going==1)
			{
				array_push($data['on_going'], $value);
			}
		}
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/projects',$data);
		
	}
	public function companyProfile()
	{
		$data = array();
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/company-profile',$data);
	}
	public function Years50()
	{
		$data = array();
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/50-plus-years-of-our-legacy',$data);
	}
	public function awards()
	{
		$data = array();
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/awards',$data);
	}
	public function careers()
	{
		$data = array();
		$this->load->view('website/careers',$data);
	}
	public function completedProject()
	{
		$data = array();
		$this->db->select('properties.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties');
		$this->db->join('cities', 'cities.id = properties.city', 'left');
		$this->db->join('locations', 'locations.id = properties.location', 'left');
		$this->db->where("active=1 AND completed = '1'");
		$this->db->order_by('sort_order');
		$query1 = $this->db->get(); 
		$properties = $query1->result();
		$data['properties'] = $properties;
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/completed-project',$data);
	}
	public function contact()
	{
		$data = array();
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/contact-us',$data);
	}
	public function gallery()
	{
		$data = array();
		$data['gallery'] = $this->Common_m->getAllDataDesc("gallery");
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/gallery',$data);
	}
	public function csr()
	{
		$data = array();
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/csr',$data);
	}
	public function groupCompany()
	{
		$data = array();
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/group-company',$data);
	}
	public function leadershipTeam()
	{
		$data = array();
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/leadership-team',$data);
	}
	public function press()
	{
		$data = array();
		$temp_property = [];
		$temp_year = [];
		$temp_month = [];
		$data['dynamic_footer'] = $this->dynamic_footer;

		//Get projects
		$this->db->select('properties.id,properties.title,press_news.project_id,press_news.article_date');
		$this->db->where('is_active',1);
		$this->db->join('properties','properties.id=press_news.project_id','left');
		$this->db->order_by('article_date','desc');
		$properties_result = $this->db->get('press_news')->result();
		if(!empty($properties_result)){
			$data['properties'] = [];
			foreach($properties_result as $key=>$value){
				if(!in_array($value->project_id, $temp_property)){
					array_push($temp_property, $value->project_id);
					array_push($data['properties'], $value);
				}
			}
		}

		//Get all press year
		$this->db->select('YEAR(article_date) as article_year');
		$this->db->order_by('YEAR(article_date)','desc');
		$this->db->where('is_active',1);
		$year_result = $this->db->get('press_news')->result_array();
		if(!empty($year_result)){
			foreach($year_result as $yKey=>$yVal){
				if(!in_array($yVal['article_year'], $temp_year)){
					array_push($temp_year, $yVal['article_year']);
				}
			}
			$data['press_year'] = $temp_year;
		}

		//Get all press month
		$this->db->select('MONTH(article_date) as month_no,DATE_FORMAT(article_date,"%M") as month_name');
		$this->db->order_by('MONTH(article_date)');
		$this->db->where('is_active',1);
		$month_result = $this->db->get('press_news')->result();
		if(!empty($month_result)){
			$data['press_month'] = [];
			foreach($month_result as $mKey=>$mVal){
				if(!in_array($mVal->month_no, $temp_month)){
					array_push($temp_month, $mVal->month_no);
					array_push($data['press_month'], $mVal);
				}
			}
		}

		$this->load->view('website/press',$data);
	}

	public function blog()
	{
		$data = array();
		$temp_property = [];
		$temp_year = [];
		$temp_month = [];
		$data['dynamic_footer'] = $this->dynamic_footer;

		//Get all blog year
		$this->db->select('YEAR(article_date) as article_year');
		$this->db->order_by('YEAR(article_date)','desc');
		$this->db->where('is_active',1);
		$year_result = $this->db->get('blog')->result_array();
		if(!empty($year_result)){
			foreach($year_result as $yKey=>$yVal){
				if(!in_array($yVal['article_year'], $temp_year)){
					array_push($temp_year, $yVal['article_year']);
				}
			}
			$data['blog_year'] = $temp_year;
		}

		//Get all blog month
		$this->db->select('MONTH(article_date) as month_no,DATE_FORMAT(article_date,"%M") as month_name');
		$this->db->order_by('MONTH(article_date)');
		$this->db->where('is_active',1);
		$month_result = $this->db->get('blog')->result();
		if(!empty($month_result)){
			$data['blog_month'] = [];
			foreach($month_result as $mKey=>$mVal){
				if(!in_array($mVal->month_no, $temp_month)){
					array_push($temp_month, $mVal->month_no);
					array_push($data['blog_month'], $mVal);
				}
			}
			
		}

		$this->load->view('website/blog',$data);
	}
	
	public function blog_detail($id){ 
		$data['id'] = $id;
		$query = "SELECT blog.* FROM blog WHERE id=$id";
		$result = $this->db->query($query);
        $data= $result->result();
        $this->load->view('website/blog_detail',['data'=> $data]);

		
        
		// $datas['blog'];

		// $blog= json_encode($data);
		
		
	}
	public function load_blog_data(){
      //print_r($_POST);
		// exit;
			if(isset($_POST) && !empty($_POST)){
			$filter["limit"] = "";
			$page = $_POST['page'];
			$resultsPerPage = 12;
			$limit = $like = "";
			$conditions = "1 = 1";

			if(!isset($_POST["page"]) || (isset($_POST["page"]) && $_POST["page"] == "") ){
				$page = 1;
			}
			$offset = ($page - 1) * $resultsPerPage;

			if(!isset($filter["limit"]) || (isset($filter["limit"]) && $filter["limit"] == "") ){
				$filter["limit"] = $resultsPerPage;
			}

			if(isset($filter['limit']) && $filter['limit']){
	            $limit = 'LIMIT '.$offset.', '.$filter['limit'];
	        }

			
			if(isset($_POST['month']) && !empty($_POST['month'])){
				$conditions .= " AND MONTH(article_date) = {$_POST['month']}";
			}
			if(isset($_POST['year']) && !empty($_POST['year'])){
				$conditions .= " AND YEAR(article_date) = {$_POST['year']}";
			}
			if(isset($_POST['search_filter']) && !empty($_POST['search_filter'])){
				$conditions .= " AND blog.title LIKE '%{$_POST['search_filter']}%'";
			}

			$query = "SELECT blog.* FROM blog WHERE {$conditions} AND is_active=1 ORDER by blog.article_date DESC ";
           $total_count = 0;
	        if(isset($filter['count'])){
	            $total_count = $filter['count'];
	        }else{
	            $count_result = $this->db->query($query);
	            $total_count = $count_result->num_rows();
	        }

	        $last_page = $total_count / $resultsPerPage;
			$last_page = ceil($last_page);

	        $query .= " $limit";
	        $result = $this->db->query($query);

	        if($result->num_rows() != 0){
				
	        	$year_element = "";
	        	$month_element = "";
	        	$datas['blog'] = $result->result();
				// echo  json_encode($datas); die; 
				
				// echo json_encode($returnArr, TRUE); die; 
		        $array1["pagination"]["page"] = $page;
				$array1["pagination"]["total_count"] = $total_count;
				$array1["pagination"]["last_page"] = $last_page;
				
	        	$returnArr['year_element'] = $year_element;
	        	$returnArr['month_element'] = $month_element;
	        	$returnArr['errCode'] = -1;

	        	$returnArr["blog"] = $this->load->view("website/blog_box_data",$datas,true);
				$returnArr["pagination"] = $this->load->view("website/blog_pagination", $array1, TRUE);
				echo json_encode($returnArr, TRUE);
				// echo $returnArr["blog"].$returnArr["pagination"];
	        }else{
	        	$returnArr["errCode"] = 1;
				$returnArr["message"] = "Sorry, no result found!";
				$returnArr["total_count"] = 0;
				echo json_encode($returnArr, TRUE);
	        }
		}
	}

	
	public function load_press_data(){
		if(isset($_POST) && !empty($_POST)){
			$filter["limit"] = "";
			$page = $_POST['page'];
			$resultsPerPage = 12;
			$limit = $like = "";
			$conditions = "1 = 1";

			if(!isset($_POST["page"]) || (isset($_POST["page"]) && $_POST["page"] == "") ){
				$page = 1;
			}
			$offset = ($page - 1) * $resultsPerPage;

			if(!isset($filter["limit"]) || (isset($filter["limit"]) && $filter["limit"] == "") ){
				$filter["limit"] = $resultsPerPage;
			}

			if(isset($filter['limit']) && $filter['limit']){
	            $limit = 'LIMIT '.$offset.', '.$filter['limit'];
	        }

			if(isset($_POST['project_id']) && !empty($_POST['project_id'])){
				$conditions .= " AND press_news.project_id = {$_POST['project_id']}";
			}
			if(isset($_POST['month']) && !empty($_POST['month'])){
				$conditions .= " AND MONTH(article_date) = {$_POST['month']}";
			}
			if(isset($_POST['year']) && !empty($_POST['year'])){
				$conditions .= " AND YEAR(article_date) = {$_POST['year']}";
			}
			if(isset($_POST['search_filter']) && !empty($_POST['search_filter'])){
				$conditions .= " AND press_news.title LIKE '%{$_POST['search_filter']}%'";
			}

			$query = "SELECT properties.id as property_id,properties.title as property_title,press_news.* FROM press_news LEFT JOIN properties ON properties.id=press_news.project_id WHERE {$conditions} AND is_active=1 ORDER by press_news.article_date DESC";

			$total_count = 0;
	        if(isset($filter['count'])){
	            $total_count = $filter['count'];
	        }else{
	            $count_result = $this->db->query($query);
	            $total_count = $count_result->num_rows();
	        }

	        $last_page = $total_count / $resultsPerPage;
			$last_page = ceil($last_page);

	        $query .= " $limit";
	        $result = $this->db->query($query);

	        if($result->num_rows() != 0){
	        	$year_element = "";
	        	$month_element = "";
	        	$data = $result->result();
	        	$array['press_news'] = $data;
				$returnArr["press_news"] = $this->load->view("website/press_box", $array, TRUE);

	        	if(isset($_POST['reset_year_month']) && $_POST['reset_year_month'] == "yes"){
	        		//Reset year and month
	        		$array['reset_year'] = [];
	        		$array['reset_month'] = [];
        			$temporary_month = [];
	        		foreach($data as $key=>$value){
	        			$year_var = date('Y',strtotime($value->article_date));
	        			$month_no = date('n',strtotime($value->article_date));
	        			$month_name = date('F',strtotime($value->article_date));

	        			if(!in_array($year_var,$array['reset_year'])){
	        				array_push($array['reset_year'], $year_var);
	        			}

	        			if(!in_array($month_no,$temporary_month)){
	        				array_push($temporary_month, $month_no);
	        				$monthObj = new StdClass;
	        				$monthObj->month_no = $month_no;
	        				$monthObj->month_name = $month_name;
	        				array_push($array['reset_month'], $monthObj);
	        			}

	        		}
	        		sort($temporary_month); //In ascending order
	        		usort($array['reset_month'], function($a, $b) use ($temporary_month) {
					    return array_search($a->month_no, $temporary_month) - array_search($b->month_no, $temporary_month);
					});
	        	}
	        	if(isset($_POST['reset_month']) && $_POST['reset_month'] == "yes"){
	        		//Reset month
	        		$array['reset_month'] = [];
        			$temporary_month = [];
	        		foreach($data as $key=>$value){
	        			$month_no = date('n',strtotime($value->article_date));
	        			$month_name = date('F',strtotime($value->article_date));

	        			if(!in_array($month_no,$temporary_month)){
	        				array_push($temporary_month, $month_no);
	        				$monthObj = new StdClass;
	        				$monthObj->month_no = $month_no;
	        				$monthObj->month_name = $month_name;
	        				array_push($array['reset_month'], $monthObj);
	        			}

	        		}
	        		sort($temporary_month); //In ascending order
	        		usort($array['reset_month'], function($a, $b) use ($temporary_month) {
					    return array_search($a->month_no, $temporary_month) - array_search($b->month_no, $temporary_month);
					});
	        	}

	        	if(isset($array['reset_year']) && !empty($array['reset_year'])){
		        	$year_element .= '<li class="dropdown-item">Select Year</li>';
	        		foreach($array['reset_year'] as $ryKey=>$ryVal){
	        			$year_element .= '<li class="dropdown-item">'.$ryVal.'</li>';
	        		}
	        	}
	        	if(isset($array['reset_month']) && !empty($array['reset_month'])){
		        	$month_element .= '<li class="dropdown-item" data-id="0">Select Month</li>';
	        		foreach($array['reset_month'] as $rmKey=>$rmVal){
	        			$month_element .= '<li class="dropdown-item" data-id="'.$rmVal->month_no.'">'.$rmVal->month_name.'</li>';
	        		}
	        	}

		        $array1["pagination"]["page"] = $page;
				$array1["pagination"]["total_count"] = $total_count;
				$array1["pagination"]["last_page"] = $last_page;
				$returnArr["pagination"] = $this->load->view("website/press_pagination", $array1, TRUE);

	        	$returnArr['year_element'] = $year_element;
	        	$returnArr['month_element'] = $month_element;
	        	$returnArr['errCode'] = -1;
				echo json_encode($returnArr, TRUE);
	        }else{
	        	$returnArr["errCode"] = 1;
				$returnArr["message"] = "Sorry, no result found!";
				$returnArr["total_count"] = 0;
				echo json_encode($returnArr, TRUE);
	        }
		}
	}

	public function sustainability()
	{
		$data = array();
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/sustainability',$data);
	}
	public function privacyPolicy()
	{
		$data = array();
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/privacy-policy',$data);
	}
	public function refundPolicy()
	{
		$data = array();
		$data['dynamic_footer'] = $this->dynamic_footer;
		$this->load->view('website/refund-policy',$data);
	}
	public function thank_you(){
		if(isset($_SESSION['thankyou']) && $_SESSION['thankyou'] == "page"){
			$data = [];

			if(isset($_SESSION['url_last_val']) && $_SESSION['url_last_val'] != ""){
				$last_val = $_SESSION['url_last_val'];
				$this->db->select('properties.*,cities.title as city_name,locations.location as location_name');
				$this->db->from('properties');
				$this->db->join('cities', 'cities.id = properties.city', 'left');
				$this->db->join('locations', 'properties.location = locations.id', 'left');
				if(is_numeric($last_val)){
					$this->db->where('properties.id', $last_val);
				}else{
					$this->db->where('properties.slug', $last_val);
				}
				$query1 = $this->db->get(); 
				$properties = $query1->row();
				if(!empty($properties)){
					$data['properties'] = $query1->result()[0];
				}
			}

			$_SESSION['thankyou'] = "page";
			$this->load->view('website/thank-you',$data);
		}else{
			redirect(base_url());
		}
	}
	public function salesforce()
	{

	
		// Dynamic Thank you URL
		$page_url=$_SERVER['HTTP_REFERER'];
		$path = parse_url($page_url, PHP_URL_PATH);
		$page_url_arr = explode("/", $path);
		$url_last_val = array_pop($page_url_arr);
		$thankyou_slug = "thank-you";
		if(!empty($url_last_val)){
			$thankyou_slug = $url_last_val."-thank-you";
		}

		$data1 = array();
		$data = file_get_contents('php://input');
		$json = json_decode($data, true);
		$name = $json['b_name'];
		$email = $json['b_email'];
		$contactno = $json['b_contactno'];
		$whatsapp = $json['b_whatsapp'];
		$device_type = $json['device_type'];
		$country = $json['country'];
		// $utm_source = $json['utm_source'];
		$utm_source = isset($_SESSION['HTTP_REFERER']) ? $_SESSION['HTTP_REFERER'] : '';
		$utm_medium = isset($_SESSION['UTM_MEDIUM']) ? $_SESSION['UTM_MEDIUM'] : '';
		$utm_campaign = isset($_SESSION['UTM_CAMPAIGN']) ? $_SESSION['UTM_CAMPAIGN'] : '';
		$gclid = isset($_SESSION['gclid']) ? $_SESSION['gclid'] : '';
		// $utm_campaign = $json['utm_campaign'];
		// $utm_medium = $json['utm_medium'];

		$url = $json['url'];
		$csrf_token = $json['csrf_token'];

		/*otpstatus*/ 
			
		$urll = $this->uri->segment(2);
		$idmpid = $_SESSION['propertiesid'];
		$queryotp = "SELECT * FROM `properties` WHERE id=$idmpid";
		$resultotp = $this->db->query($queryotp);
		$dataotp= $resultotp->result();
		$optstatus = $dataotp[0]->otpstatus;
		 if($optstatus ==1){
			$mobile = $contactno;
			$email = $email;
			$_SESSION['otpemail'] = $email;
			$_SESSION['otpmobile'] = $mobile;
				$FourDigitRandomNumber = rand(1231,7879);
					$msg = urlencode('Dear Customer, '.$FourDigitRandomNumber.' is the OTP for mobile number verification. Thank you for your enquiry on Kalpataru website');
					$_SESSION['otpmessage']=$FourDigitRandomNumber;
			//	mail($email,"Kalpataru OTP Verification",$msg);	
				$to = $email;
					$subject = "Kalpataru OTP Verification";
					
					$message = 'Dear Customer, '.$FourDigitRandomNumber.' is the OTP for mobile number verification. Thank you for your enquiry on Kalpataru website';
					
					$header = "From:info@kalpataru.com \r\n";
					$header .= "MIME-Version: 1.0\r\n";
					$header .= "Content-type: text/html\r\n";
					
					$retval = mail ($to,$subject,$message,$header);
			
			$url = "https://japi.instaalerts.zone/httpapi/QueryStringReceiver?ver=1.0&key=vXAIgk0KcIlZT0jpBRSUTQ==&encrpt=0&dest=91".$mobile."&send=KPTARU&text=".$msg."&dlt_entity_id=1101596160000018848&dlt_template_id=1107166451486488105";
			$newCurl = curl_init();
			curl_setopt($newCurl, CURLOPT_URL, $url);
			curl_setopt($newCurl, CURLOPT_RETURNTRANSFER, true);
			$output = curl_exec($newCurl);
				}
	/*	otpstatus*/
		
		if(checkToken($csrf_token,'book_now')){

			//error_reporting(0);

			// $name = "Utsav Devadhia";
			// $email = "utsav@hepta.me";
			// $mobile = "9890054659";
			// $whatsapp = 1;

			// $priject_name = $json['priject_name'];
			// $url = "http://localhost/kalpataru/projects/commercial/immensa/1";

			/*echo $final_url = 'https://CS73.salesforce.com/servlet/servlet.WebToLead?encoding=UTF-8?&last_name='.$name.'&email='.$email.'&mobile='.$contactno.'&00N2800000IQrnD=India&oid=00D6D0000000klA&lead_source=KL Website&00N2800000IQron=Internet&00N0K00000JDyRp=Facebook&00N0K00000JDyRu=&00N2800000IQroh=Web to Lead&00N2800000IQron=Internet&00N2800000IQrma='.$whatsapp.'&00N0K00000K2TIG=Chrome 86&00N0K00000K2Tgm=Mobile&00N0K00000JDyS9='.$url;

			$curl = curl_init($final_url);
			curl_setopt($curl, CURLOPT_FAILONERROR, true);
			curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);  
			$result = curl_exec($curl);
			echo $result;*/

			$this->load->library('user_agent');
			$browser = $this->agent->browser();
			$browserVersion = (int)($this->agent->version());
			$platform = $this->agent->platform();

			$final_browser_info = $browser.' '.$browserVersion;

			$api_request = $api_url = $this->config->item('sfdc_url');

			$api_request .= "?";

			$api_data["last_name"] = $name;
			$api_request .= "last_name=".$name;

			$api_data["email"] = $email;
			$api_request .= "&email=".$email;

			$api_data["mobile"] = $contactno;
			$api_request .= "&mobile=".$contactno;

			$api_data["00N2800000IQrnD"] = $country;
			$api_request .= "&00N2800000IQrnD=".$country;

			$api_data["oid"] = $this->config->item('oid');
			$api_request .= "&oid=".$this->config->item('oid');

			$api_data["lead_source"] = "KL Website";
			$api_request .= "&lead_source="."KL Website";

			$api_data["00N2800000IQron"] = "Internet";
			$api_request .= "&00N2800000IQron="."Internet";

			$api_data["00N2800000IQrma"] = $whatsapp;
			$api_request .= "&00N2800000IQrma=".$whatsapp;

			if($utm_source != ""){
				$api_data["00N0K00000JDyRp"] = $utm_source;
				$api_request .= "&00N0K00000JDyRp=".$utm_source;
			}
			if($utm_medium != ""){
				$api_data["00N0K00000JDyRz"] = $utm_medium;
				$api_request .= "&00N0K00000JDyRz=".$utm_medium;
			}
			if($utm_campaign != ""){
				$api_data["00N0K00000JDyRu"] = $utm_campaign;
				$api_request .= "&00N0K00000JDyRu=".$utm_campaign;
			}
			if($gclid != ""){
				$api_data["00N2800000IQrme"] = $gclid;
				$api_request .= "&00N2800000IQrme=".$gclid;
			}
			if($final_browser_info != ""){
				$api_data["00N0K00000K2TIG"] = $final_browser_info;
				$api_request .= "&00N0K00000K2TIG=".$final_browser_info;
			}

			if($device_type != ""){
				$api_data["00N0K00000K2Tgm"] = $device_type;
				$api_request .= "&00N2800000IQrma=".$whatsapp;
			}
			
			
			$api_data["00N2800000IQroh"] = "Web to Lead";
			$api_request .= "&00N2800000IQroh="."Web to Lead";

			$api_data["00N0K00000JDyS9"] = $url;
			$api_request .= "&00N0K00000JDyS9=".$url;



			$utm_site = 'Google';
			$utm_term = '2BHK';
			$utm_placement = 'Left';
			$utm_content = 'Search';
			$utm_adset = '2_bhk';
			$utm_ad = 'Responsive_Text_Ads';



			//echo $final_url = $api_url.'?&last_name='.$name.'&email='.$email.'&mobile='.$contactno.'&00N2800000IQrnD='.$country.'&oid=00D6D0000000klA&lead_source=KL Website&00N2800000IQron=Internet&00N0K00000JDyRp='.$utm_source.'&00N0K00000JDyRu='.$utm_campaign.'&00N2800000IQroh=Web to Lead&00N2800000IQron=Internet&00N2800000IQrma='.$whatsapp.'&00N0K00000K2TIG='.$final_browser_info.'&00N0K00000K2Tgm='.$device_type.'&00N0K00000JDyS9='.$url;

			$data1["last_name"] = $name;
			$data1["email"] = $email;
			$data1["mobile"] = $contactno;
			$data1["country"] = $country;
			$data1["oid"] = $api_data["oid"];
			$data1["lead_source"] = $api_data["lead_source"];
			$data1["whatsapp"] = $whatsapp;
			$data1["utm_source"] = $utm_source;
			$data1["utm_campaign"] = $utm_campaign;

			$data1["utm_site"] = $utm_site;
			$data1["utm_term"] = $utm_term;
			$data1["utm_placement"] = $utm_placement;
			$data1["utm_content"] = $utm_content;
			$data1["utm_adset"] = $utm_adset;
			$data1["utm_ad"] = $utm_ad;


			










			$data1["browser_info"] = $final_browser_info;
			$data1["device_type"] = $device_type;
			$data1["url"] = $url;
			//$data1["api_request"] = $api_url.'?&last_name='.$name.'&email='.$email.'&mobile='.$contactno.'&00N2800000IQrnD='.$country.'&oid='.$api_data["oid"].'&lead_source=KL Website&00N2800000IQron=Internet&00N0K00000JDyRp='.$utm_source.'&00N0K00000JDyRz='.$utm_medium.'&00N0K00000JDyRu='.$utm_campaign.'&00N2800000IQroh=Web to Lead&00N2800000IQron=Internet&00N2800000IQrma='.$whatsapp.'&00N0K00000K2TIG='.$final_browser_info.'&00N0K00000K2Tgm='.$device_type.'&00N0K00000JDyS9='.$url;
			$data1["api_request"] = $api_request;
			$data1["created_at"] = Date("Y-m-d H:i:s");

			$data_result = $this->db->insert("enquiries",$data1);

		   	$curl = curl_init();
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $api_data);
			curl_setopt($curl, CURLOPT_URL, $api_url);
		   	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		   	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

		   	$result = curl_exec($curl);


//echo $uur = $api_url.'?&last_name='.$name.'&email='.$email.'&mobile='.$contactno.'&00N2800000IQrnD='.$country.'&oid='.$api_data["oid"].'&lead_source=KL Website&00N2800000IQron=Internet&00N0K00000JDyRp='.$utm_source.'&00N0K00000JDyRz='.$utm_medium.'&00N0K00000JDyRu='.$utm_campaign.'&00N2800000IQroh=Web to Lead&00N2800000IQron=Internet&00N2800000IQrma='.$whatsapp.'&00N0K00000K2TIG='.$final_browser_info.'&00N0K00000K2Tgm='.$device_type.'&00N0K00000JDyS9='.$url;


   $uur = $api_url.'?&last_name='.$name.'&email='.$email.'&mobile='.$contactno.'&00N2800000IQrnD='.$country.'&oid='.$api_data["oid"].'&lead_source=KL Website&00N2800000IQron=Internet&00N0K00000JDyRp='.$utm_source.'&00N0K00000JDyRz='.$utm_medium.'&00N0K00000JDyRu='.$utm_campaign.'&00N2800000IQroh=Web to Lead&00N2800000IQron=Internet&00N2800000IQrma='.$whatsapp.'&00N0K00000K2TIG='.$final_browser_info.'&00N0K00000K2Tgm='.$device_type.'&00N0K00000JDyS9='.$url;
    $returnArr["urlm"] = $uur;
 	if (!curl_errno($curl)) {
		   		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		   		curl_close($curl);

		   		//Set session for thank you
		   		$_SESSION['thankyou'] = "page";
		   		$_SESSION['url_last_val'] = $url_last_val;

		   		$returnArr["status"] = TRUE;
		   		$returnArr["message"] = "Success";
		   		$returnArr['thankyou_slug'] = $thankyou_slug;

		   		echo json_encode($returnArr);
		  	}else{

		   		$returnArr["status"] = FALSE;
		   		$returnArr["message"] = "Failed to submit request";
		   		$returnArr['thankyou_slug'] = $thankyou_slug;

		   		echo json_encode($returnArr);
		   	}

		}else{
			$returnArr["status"] = FALSE;
			$returnArr["message"] = "Authentication failed";
			echo json_encode($returnArr);
		}

		
	
		
	}






/*nri salesforce*/

public function nrisalesforce()
	{

	
		// Dynamic Thank you URL
		$page_url=$_SERVER['HTTP_REFERER'];
		$path = parse_url($page_url, PHP_URL_PATH);
		$page_url_arr = explode("/", $path);
		$url_last_val = array_pop($page_url_arr);
		$thankyou_slug = "thank-you";
		if(!empty($url_last_val)){
			$thankyou_slug = $url_last_val."-thank-you";
		}

		$data1 = array();
		$data = file_get_contents('php://input');
		$json = json_decode($data, true);
		$name = $json['b_name'];
		$email = $json['b_email'];
		$contactno = $json['b_contactno'];
		$whatsapp = $json['b_whatsapp'];
		$device_type = $json['device_type'];
		$country = $json['country'];
		// $utm_source = $json['utm_source'];
		$utm_source = isset($_SESSION['HTTP_REFERER']) ? $_SESSION['HTTP_REFERER'] : '';
		$utm_medium = isset($_SESSION['UTM_MEDIUM']) ? $_SESSION['UTM_MEDIUM'] : '';
		$utm_campaign = isset($_SESSION['UTM_CAMPAIGN']) ? $_SESSION['UTM_CAMPAIGN'] : '';
		$gclid = isset($_SESSION['gclid']) ? $_SESSION['gclid'] : '';
		// $utm_campaign = $json['utm_campaign'];
		// $utm_medium = $json['utm_medium'];

		$url = $json['url'];
		$csrf_token = $json['csrf_token'];

		
		
		if(checkToken($csrf_token,'book_now')){
			$this->load->library('user_agent');
			$browser = $this->agent->browser();
			$browserVersion = (int)($this->agent->version());
			$platform = $this->agent->platform();
			$final_browser_info = $browser.' '.$browserVersion;
			$api_request = $api_url = $this->config->item('sfdc_url');
			$api_request .= "?";
			$api_data["last_name"] = $name;
			$api_request .= "last_name=".$name;
			$api_data["email"] = $email;
			$api_request .= "&email=".$email;
			$api_data["mobile"] = $contactno;
			$api_request .= "&mobile=".$contactno;
			$api_data["00N2800000IQrnD"] = $country;
			$api_request .= "&00N2800000IQrnD=".$country;
			$api_data["oid"] = $this->config->item('oid');
			$api_request .= "&oid=".$this->config->item('oid');
			$api_data["lead_source"] = "KL Website";
			$api_request .= "&lead_source="."KL Website";
			$api_data["00N2800000IQron"] = "Internet";
			$api_request .= "&00N2800000IQron="."Internet";
			$api_data["00N2800000IQrma"] = $whatsapp;
			$api_request .= "&00N2800000IQrma=".$whatsapp;
			if($utm_source != ""){
				$api_data["00N0K00000JDyRp"] = $utm_source;
				$api_request .= "&00N0K00000JDyRp=".$utm_source;
			}
			if($utm_medium != ""){
				$api_data["00N0K00000JDyRz"] = $utm_medium;
				$api_request .= "&00N0K00000JDyRz=".$utm_medium;
			}
			if($utm_campaign != ""){
				$api_data["00N0K00000JDyRu"] = $utm_campaign;
				$api_request .= "&00N0K00000JDyRu=".$utm_campaign;
			}
			if($gclid != ""){
				$api_data["00N2800000IQrme"] = $gclid;
				$api_request .= "&00N2800000IQrme=".$gclid;
			}
			if($final_browser_info != ""){
				$api_data["00N0K00000K2TIG"] = $final_browser_info;
				$api_request .= "&00N0K00000K2TIG=".$final_browser_info;
			}
			if($device_type != ""){
				$api_data["00N0K00000K2Tgm"] = $device_type;
				$api_request .= "&00N2800000IQrma=".$whatsapp;
			}
		
			$api_data["00N2800000IQroh"] = "Web to Lead";
			$api_request .= "&00N2800000IQroh="."Web to Lead";
			$api_data["00N0K00000JDyS9"] = $url;
			$api_request .= "&00N0K00000JDyS9=".$url;
			$utm_site = 'Google';
			$utm_term = '2BHK';
			$utm_placement = 'Left';
			$utm_content = 'Search';
			$utm_adset = '2_bhk';
			$utm_ad = 'Responsive_Text_Ads';
			//echo $final_url = $api_url.'?&last_name='.$name.'&email='.$email.'&mobile='.$contactno.'&00N2800000IQrnD='.$country.'&oid=00D6D0000000klA&lead_source=KL Website&00N2800000IQron=Internet&00N0K00000JDyRp='.$utm_source.'&00N0K00000JDyRu='.$utm_campaign.'&00N2800000IQroh=Web to Lead&00N2800000IQron=Internet&00N2800000IQrma='.$whatsapp.'&00N0K00000K2TIG='.$final_browser_info.'&00N0K00000K2Tgm='.$device_type.'&00N0K00000JDyS9='.$url;
			$data1["last_name"] = $name;
			$data1["email"] = $email;
			$data1["mobile"] = $contactno;
			$data1["country"] = $country;
			$data1["oid"] = $api_data["oid"];
			$data1["lead_source"] = $api_data["lead_source"];
			$data1["whatsapp"] = $whatsapp;
			$data1["utm_source"] = $utm_source;
			$data1["utm_campaign"] = $utm_campaign;
			$data1["utm_site"] = $utm_site;
			$data1["utm_term"] = $utm_term;
			$data1["utm_placement"] = $utm_placement;
			$data1["utm_content"] = $utm_content;
			$data1["utm_adset"] = $utm_adset;
			$data1["utm_ad"] = $utm_ad;
		$data1["browser_info"] = $final_browser_info;
			$data1["device_type"] = $device_type;
			$data1["url"] = $url;
			$data1["api_request"] = $api_request;
			$data1["created_at"] = Date("Y-m-d H:i:s");
			$data_result = $this->db->insert("enquiries",$data1);
		   	$curl = curl_init();
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $api_data);
			curl_setopt($curl, CURLOPT_URL, $api_url);
		   	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		   	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		   	$result = curl_exec($curl);
            $uur = $api_url.'?&last_name='.$name.'&email='.$email.'&mobile='.$contactno.'&00N2800000IQrnD='.$country.'&oid='.$api_data["oid"].'&lead_source=KL Website&00N2800000IQron=Internet&00N0K00000JDyRp='.$utm_source.'&00N0K00000JDyRz='.$utm_medium.'&00N0K00000JDyRu='.$utm_campaign.'&00N2800000IQroh=Web to Lead&00N2800000IQron=Internet&00N2800000IQrma='.$whatsapp.'&00N0K00000K2TIG='.$final_browser_info.'&00N0K00000K2Tgm='.$device_type.'&00N0K00000JDyS9='.$url;
            $returnArr["urlm"] = $uur;
			if (!curl_errno($curl)) {
		   		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		   		curl_close($curl);
		   		//Set session for thank you
		   		$_SESSION['thankyou'] = "page";
		   		$_SESSION['url_last_val'] = $url_last_val;
		   		$returnArr["status"] = TRUE;
		   		$returnArr["message"] = "Success";
		   		$returnArr['thankyou_slug'] = $thankyou_slug;
		   		echo json_encode($returnArr);
		 	}else{

		   		$returnArr["status"] = FALSE;
		   		$returnArr["message"] = "Failed to submit request";
		   		$returnArr['thankyou_slug'] = $thankyou_slug;

		   		echo json_encode($returnArr);
		   	}

		}else{
			$returnArr["status"] = FALSE;
			$returnArr["message"] = "Authentication failed";
			echo json_encode($returnArr);
		}
	
	}

	/*nri salesforce*/















    /* home page search */

    public function project_search()
    {

        $search_box = trim($this->input->get('search_box'));

        $data['property'] = $this->Common_m->get_search_property_data($search_box); //, $search_city, $search_location, $typology

        $this->load->view('website/property-search-result',$data);
    }

    public function fetch()
    {
        echo $this->Common_m->fetch_data($this->uri->segment(3));
    }



    // ============================= Static Page corporate governance ==========================================

    public function corporateGovernance()
    {
        $this->load->view('website/investor');
    }

//rks

	public function investorpage()
	{
		$data = array();
	
		$data['dynamic_footer'] = $this->dynamic_footer;

		//Get all blog year
		
		//cat
	
		$this->db->select('*');
		$this->db->from('investor_category');
	$query1 = $this->db->get(); 
		$data['invcategory'] = $query1->result();
		
		//cat
		
			
		//subcat
	
		$this->db->select('*');
		$this->db->from('investor_subcategory');
	$query1 = $this->db->get(); 
		$data['invsubcategory'] = $query1->result();
		
		//subcat
		
		//year
	
		$this->db->select('*');
		$this->db->from('investor_year');
	$query1 = $this->db->get(); 
		$data['invyear'] = $query1->result();
		
		//year
			
		
			    $this->db->select('*');
				$this->db->from('investor_documents');
				$this->db->join('investor_category', 'investor_documents.investor_category = investor_category.id', 'left');
				$this->db->join('investor_subcategory', 'investor_documents.investor_subcategory = investor_subcategory.id', 'left');
			    $this->db->join('investor_year', 'investor_documents.investor_year = investor_year.id', 'left');
			    //echo $this->db->last_query();
				$query1 = $this->db->get(); 
				//	echo $this->db->last_query();
				//die();
				$investordoc = $query1->row();
				$data['investordoc'] = $query1->result();
			
				$this->load->view('website/investorpage',$data);
	}
	



		public function otpapprove()
	{
	    
	    	$page_url=$_SERVER['HTTP_REFERER'];
		$path = parse_url($page_url, PHP_URL_PATH);
		$page_url_arr = explode("/", $path);
		$url_last_val = array_pop($page_url_arr);
		$thankyou_slug = "thank-you";
		if(!empty($url_last_val)){
			$thankyou_slug = $url_last_val."-thank-you";
		}

	    		$data = file_get_contents('php://input');
		$json = json_decode($data, true);
	    $otp_code = $json['otp_code'];	
$otpmessage =	$_SESSION['otpmessage'];
$otpemail =	$_SESSION['otpemail'];
$otpmobile =	$_SESSION['otpmobile'];


	     $returnArr["otp_code"] = $otpmessage;
	      $returnArr["otpmessage"] = $otp_code;
	      	$returnArr['thankyou_slug'] = $thankyou_slug;
	    if($otp_code==$otpmessage)
	    {


$qry = "update `enquiries` set otpstatus = '1' where email = '$otpemail' and mobile='$otpmobile'";
$query = $this->db->query($qry);

	      $returnArr["message"] = "otpsuccess";  
	        
	    }
	    else
	    {
	        
	        $returnArr["message"] = "otpfail"; 
	    }
	    
	    
		
			echo json_encode($returnArr);
	}

	



	public function otpresend()
	{	
	
	
	/*otpstatus*/ 
 $idm = $_SESSION['ulrid'];
 $queryotp = "SELECT * FROM `properties` WHERE id=$idm";
 $resultotp = $this->db->query($queryotp);
  $dataotp= $resultotp->result();
$optstatus = $dataotp[0]->otpstatus;
 
 	
 	
 $mobile =$_SESSION['otpmobile'];
$email = $_SESSION['otpemail'];
 	
 	$FourDigitRandomNumber = $_SESSION['otpmessage'];
 		$msg = urlencode('Dear Customer, '.$FourDigitRandomNumber.' is the OTP for mobile number verification. Thank you for your enquiry on Kalpataru website');
 		$_SESSION['otpmessage']=$FourDigitRandomNumber;
 //	mail($email,"Kalpataru OTP Verification",$msg);	
	$to = $email;
         $subject = "Kalpataru OTP Verification";
         
         $message = 'Dear Customer, '.$FourDigitRandomNumber.' is the OTP for mobile number verification. Thank you for your enquiry on Kalpataru website';
         
         $header = "From:info@kalpataru.com \r\n";
         $header .= "Cc:info@kalpataru.com \r\n";
         $header .= "MIME-Version: 1.0\r\n";
         $header .= "Content-type: text/html\r\n";
         
         $retval = mail ($to,$subject,$message,$header);
 
 $url = "https://japi.instaalerts.zone/httpapi/QueryStringReceiver?ver=1.0&key=vXAIgk0KcIlZT0jpBRSUTQ==&encrpt=0&dest=91".$mobile."&send=KPTARU&text=".$msg."&dlt_entity_id=1101596160000018848&dlt_template_id=1107166451486488105";
 $newCurl = curl_init();
curl_setopt($newCurl, CURLOPT_URL, $url);
curl_setopt($newCurl, CURLOPT_RETURNTRANSFER, true);
 $output = curl_exec($newCurl);
	
	
	  $returnArr["message"] = "otpresended";  
	  echo json_encode($returnArr);
	}




	public function savegetintouch()
	{
		
		$data = array(
			"book_gettouch_name" => $this->input->post('book_gettouch_name'), 
            "book_gettouch_email"=>$this->input->post('book_gettouch_email'),
			"book_gettouch_property"=>$this->input->post('book_gettouch_property'),
			"country_code"=>$this->input->post('country_code'),
			"book_gettouch_contact_number"=>$this->input->post('book_gettouch_contact_number')

		);
		$status = array();
		$result=$this->Common_m->addData("nri_enquiry",$data);
		if($this->db->affected_rows()>0)
		{
			$_SESSION['thankyou'] = "page";
			redirect('thank-you');
			
		}
		else{
			$this->load->view('website/kalpatarunri');
		}
		
 
	}
	
	
// 	public function kalpatarunri()
// 	{
// 		$data = array();
// 		$this->db->select('properties_nri.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
// 		$this->db->from('properties_nri');
// 		$this->db->join('cities', 'cities.id = properties_nri.city', 'left');
// 		$this->db->join('locations', 'locations.id = properties_nri.location', 'left');
// 		$this->db->where('active=1 AND slider=1');
// 		$this->db->order_by("slider_order", "asc");
// 		$query1 = $this->db->get(); 
// 		//echo $this->db->last_query();
// 		$properties = $query1->result();
// 		$data['slider'] = array();
// 		$data['banner_type'] = array();
// 		$data['cities'] = $this->Common_m->getAllData("cities");
// 		foreach ($properties as $key => $value) {

// 			$value->google_pixel = "";
// 			$value->facebook_pixel = "";

// 			if ($value->slider==1) {
// 				array_push($data['slider'], $value);
// 				array_push($data['banner_type'], $value->banner_type);
// 			}
// 		}



// 		$this->db->select('properties_nri.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
// 		$this->db->from('properties_nri');
// 		$this->db->join('cities', 'cities.id = properties_nri.city', 'left');
// 		$this->db->join('locations', 'locations.id = properties_nri.location', 'left');
// 		$this->db->where('active=1 AND featured=1');
// 		$this->db->order_by("featured_order", "asc");
// 		$query1 = $this->db->get(); 
// 		$properties = $query1->result();
// 		$data['featured'] = array();
// 		foreach ($properties as $key => $value) {

// 			$value->google_pixel = "";
// 			$value->facebook_pixel = "";
// 			if($value->featured==1)
// 			{
// 				array_push($data['featured'], $value);
// 			}
			
			
// 		}






// 		$this->db->select('properties_nri.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
// 		$this->db->from('properties_nri');
// 		$this->db->join('cities', 'cities.id = properties_nri.city', 'left');
// 		$this->db->join('locations', 'locations.id = properties_nri.location', 'left');
// 		$this->db->where('active=1');
// 		$query1 = $this->db->get(); 
// 		$properties = $query1->result();
// 		$data['properties'] = array();
// 		foreach ($properties as $key => $value) {

// 			$value->google_pixel = "";
// 			$value->facebook_pixel = "";
			
// 				array_push($data['properties'], $value);
		
			
			
// 		}









// 		$page_url=current_url();
// 		$page_url_arr = explode("/", $page_url);
// 		$last_val = array_pop($page_url_arr);

// 		$this->db->select('properties_nri.*,cities.title as city_name,locations.location as location_name');
// 		$this->db->from('properties_nri');
// 		$this->db->join('cities', 'cities.id = properties_nri.city', 'left');
// 		$this->db->join('locations', 'properties_nri.location = locations.id', 'left');
// 		if(is_numeric($last_val)){
// 			$this->db->where('properties_nri.id', $last_val);
// 		}else{
// 			$this->db->where('properties_nri.slug', $last_val);
// 		}
// 		$query1 = $this->db->get(); 
// 		$properties = $query1->row();
// 		if(!empty($properties)){
// 			$data['properties'] = $query1->result()[0];
// 			$am = isset($properties->overview_amenity) ? $properties->overview_amenity : "'noamenity'";
// 			if ($am == "") {
// 				$data['amenities'] = array();
// 			}	
// 			else
// 			{
// 				$amenities = $this->Common_m->getAllDataWithCondition("amenities","id IN ($am)");
// 				$data['amenities'] = $amenities;
// 			}
// 			$data['dynamic_footer'] = $this->Common_m->getFooter($data['properties']->city_name);
// 						$this->load->view('website/kalpatarunri',$data);
// 		}else{
// 			$this->load->view('website/err404');
// 		}



// 		//Dynamic footer
// 	//	$data['dynamic_footer'] = $this->dynamic_footer;
// 	//	$this->load->view('website/kalpatarunri',$data);
// 	}
	
	
	
// 	//rks



public function kalpatarunri()
	{
		$data = array();
		$this->db->select('properties_nri.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties_nri');
		$this->db->join('cities', 'cities.id = properties_nri.city', 'left');
		$this->db->join('locations', 'locations.id = properties_nri.location', 'left');
		$this->db->where('active=1 AND slider=1');
		$this->db->order_by("slider_order", "asc");
		$query1 = $this->db->get(); 
		//echo $this->db->last_query();
		$properties = $query1->result();
		
		$this->db->select('properties_nri.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties_nri');
		$this->db->join('cities', 'cities.id = properties_nri.city', 'left');
		$this->db->join('locations', 'locations.id = properties_nri.location', 'left');
		$this->db->where('active=1 AND featured=1');
		$this->db->group_by('cities.id'); 
		$query2 = $this->db->get(); 

		//echo $this->db->last_query();
        $data2 = $query2->result();
        
		$data['slider'] = array();
		$data['banner_type'] = array();
		$data['cities'] = $this->Common_m->getAllData("cities");
		foreach ($properties as $key => $value) {

			$value->google_pixel = "";
			$value->facebook_pixel = "";

			if ($value->slider==1) {
				array_push($data['slider'], $value);
				array_push($data['banner_type'], $value->banner_type);
			}
		}



		$this->db->select('properties_nri.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties_nri');
		$this->db->join('cities', 'cities.id = properties_nri.city', 'left');
		$this->db->join('locations', 'locations.id = properties_nri.location', 'left');
		$this->db->where('active=1 AND featured=1');
		$this->db->order_by("featured_order", "asc");
		$query1 = $this->db->get(); 
		$properties = $query1->result();
		$data['featured'] = array();
		foreach ($properties as $key => $value) {

			$value->google_pixel = "";
			$value->facebook_pixel = "";
			if($value->featured==1)
			{
				array_push($data['featured'], $value);
			}
			
			
		}






		$this->db->select('properties_nri.*,cities.title as city_name,locations.location as location_name,cities.slug as city_slug,locations.slug as location_slug');
		$this->db->from('properties_nri');
		$this->db->join('cities', 'cities.id = properties_nri.city', 'left');
		$this->db->join('locations', 'locations.id = properties_nri.location', 'left');
		$this->db->where('active=1');
		$query1 = $this->db->get(); 
		$properties = $query1->result();
		$data['properties'] = array();
		foreach ($properties as $key => $value) {

			$value->google_pixel = "";
			$value->facebook_pixel = "";
			
				array_push($data['properties'], $value);
		
			
			
		}









		$page_url=current_url();
		$page_url_arr = explode("/", $page_url);
		$last_val = array_pop($page_url_arr);

		$this->db->select('properties_nri.*,cities.title as city_name,locations.location as location_name');
		$this->db->from('properties_nri');
		$this->db->join('cities', 'cities.id = properties_nri.city', 'left');
		$this->db->join('locations', 'properties_nri.location = locations.id', 'left');
		if(is_numeric($last_val)){
			$this->db->where('properties_nri.id', $last_val);
		}else{
			$this->db->where('properties_nri.slug', $last_val);
		}
		$query1 = $this->db->get(); 
		$properties = $query1->row();
		if(!empty($properties)){
			$data['properties'] = $query1->result()[0];
			$am = isset($properties->overview_amenity) ? $properties->overview_amenity : "'noamenity'";
			if ($am == "") {
				$data['amenities'] = array();
			}	
			else
			{
				$amenities = $this->Common_m->getAllDataWithCondition("amenities","id IN ($am)");
				$data['amenities'] = $amenities;
			}
			$data['dynamic_footer'] = $this->Common_m->getFooter($data['properties']->city_name);
					//	$this->load->view('website/kalpatarunri',$data);
		}else{
			$this->load->view('website/err404');
		}

        $data['datas'] = $data2;
		$this->load->view('website/kalpatarunri',$data);

		//Dynamic footer
	//	$data['dynamic_footer'] = $this->dynamic_footer;
	//	$this->load->view('website/kalpatarunri',$data);
	}


}