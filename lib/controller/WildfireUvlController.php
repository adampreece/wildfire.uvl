<?
class WildfireUvlController extends ApplicationController{

  public $paginate_dealership_list = false;
  public $paginate_vehicle_list = true;

  public $vehicle_class = "WildfireUvlVehicle";

  //pushing back to the stack
  public function controller_global(){
    WaxEvent::add("cms.cms_stack_set", function(){
      $obj = WaxEvent::data();
      array_unshift($obj->cms_stack, $obj->controller);
    });
    parent::controller_global();
    if(!$this->cms_called) $this->cms();

  }


  /**
   * handles the display, filtering, search etc of vehicles
   */

  public function __vehicle_listing(){
    $model = new $this->cms_content_class($this->cms_live_scope);
    $model = $this->__vehicle_filters($model, Request::param('vehicle'));
    if($this->paginate_vehicle_list){
      if(!$this->this_page = Request::param('page')) $this->this_page = 1;
      $this->vehicles = $model->page($this->this_page, $this->per_page);
    }else $this->vehicles = $model->all();

  }
  //find min - max values for search options, custom search fields etc
  public function __vehicle_search_options($cache=true, $return=false){
    //this could be a very slow query, lots of db look ups, so add in some caching
    if($cache && ($cached = $this->__uvl_cache("__vehicle_search_options"))) $search_options = $cached;
    else $search_options = array();
    
    if(!$search_options){
      $model = new WildfireUvlVehicleSearchField;
      foreach($model->all() as $search){
        $opt =  array('col'=>$search->column_name, 'title'=>$search->title, 'type'=>$search->search_type, 'inc'=>$search->increment);
        if($search->search_type == "range") $opt['range'] = $this->__vehicle_search_range_values(new $this->vehicle_class, $search->column_name);
        else $opt['options'] = $this->__vehicle_search_select_options(new $this->vehicle_class, $search->column_name);
        $search_options[$search->column_name] = $opt;
      }
    }
    $this->search_options = $search_options;
    if($return) return $this->search_options;
  }
  //this is a range column, so we just look for the min & max values on the db
  protected function __vehicle_search_range_values($model, $column){
    //find min & max of this column
    $wax_model = new WaxModel;
    $sql = "SELECT DISTINCT MIN(`$column`) as minval, MAX(`$column`) as maxval FROM ".$model->table." WHERE `$column` > 0";
    $res = $wax_model->query($sql)->fetchAll();
    return array('min'=>$res[0]['minval'], 'max'=>$res[0]['maxval']);
  }
  //this goes over a join, so we have multiple options to look at
  protected function __vehicle_search_select_options($model, $join_name){
    $options = array();
    //join
    if($j_class = $model->columns[$join_name][1]['target_model']){
      $join_model = new $j_class($this->cms_content_scope);
      foreach($join_model->all() as $row) $options[] = array('title'=>$row->humanize(), 'primval'=>$row->primval);
    }else{
      //assume its a column, so grab all the versions of that col from this table
      $class = get_class($model);
      $cloned = new $class($this->cms_content_scope);
      foreach($cloned->group($join_name)->all() as $item) $options[] = array('title'=>$item->$join_name, 'primval'=>urlencode($item->$join_name));
    }
    return $options;
  }


  //small on used on the listing
  public function __vehicle_summary(){}
  //main one
  public function __vehicle(){}

  protected function __vehicle_filters($model){
    return $model;
  }

  /**
   * handle dealership lookups
   */
  public function __dealership_listing(){
    /**
     * will keep this one simple, just list all dealership branches
     */
    $model = new $this->cms_content_class($this->cms_live_scope);
    $model = $this->__dealership_filters($model);
    if($this->paginate_dealership_list){
      if(!$this->this_page = Request::param('page')) $this->this_page = 1;
      $this->dealerships = $model->page($this->this_page, $this->per_page);
    }else $this->dealerships = $model->all();
  }
  public function __dealership_summary(){}
  public function __dealership(){}

  protected function __dealership_filters($model){ return $model; }

  /**
   * a small cache helper for the slow queries that runs on memcached 
   */
  protected function __uvl_cache($func, $lifetime=3600){
    if(class_exists("Memcache", false)){
      $store = new WaxCache("wuvl/".$func, "memcache", array("lifetime"=>$lifetime));
      if($cache = $store->get()) return unserialize($cache);
      else{
        $value = $this->$func(false, true);
        $store->set(serialize($value));
        return $value;
      }
    }
    return false;
  }
}
?>