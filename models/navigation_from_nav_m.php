<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Navigation model for the navigation module.
 * 
 * @author		Phil Sturgeon
 * @author		PyroCMS Dev Team
 * @package		PyroCMS\Core\Modules\Navigation\Models
 */
class Navigation_from_nav_m extends MY_Model
{    
	public function __construct()
	{
		parent::__construct();
		
		$this->_table = 'navigation_links';
		
		class_exists('Navigation_m') OR $this->load->model('navigation/navigation_m');
	}
	
	/**
	 * Build a multi-array of parent (navigation) > children (pages)
	 *
	 * @author Yohann Decharraud - Jerel Unruh - PyroCMS Dev Team
	 * 
	 * @param  string $group Either the group abbrev or the group id
	 * @return array An array representing the link tree
	 */
	public function get_link_tree_from_nav($group, $params = array())
	{
		// the plugin passes the abbreviation
		if ( ! is_numeric($group))
		{
			$row = $this->navigation_m->get_group_by('abbrev', $group);
			$group = $row ? $row->id : null;
		}
		
		if ( ! empty($params['order']))
		{
			$this->db->order_by($params['order']);
		}
		else
		{
			$this->db->order_by('position');
		}
		
		if (isset($params['front_end']) and $params['front_end'])
		{
			$front_end = true;
		}
		else
		{
			$front_end = false;
		}
		
		if (isset($params['user_group']))
		{
			$user_group = $params['user_group'];
		}
		else
		{
			$user_group = false;
		}

        // get entire list of pages of level 0 from navigation plugin
        // take care : ids are for navigation position, not ids of pages !
		$all_links = $this->db->where(array(
		                                  'navigation_group_id'=>$group,
		                                  'parent'=>'0')
		                              )
			 ->get($this->_table)
			 ->result_array();
		
		// réindexe les ids
		$indexed_links = array();
		foreach ($all_links as $row)
		{
			$indexed_links[$row['page_id']] = $row;
		}
		        
        // add page children
        foreach ($indexed_links as $link)
        {
            if ($link['link_type'] == 'page')
            {
                $children = $this->db->select('id,title,parent_id,order')
                    ->where('parent_id',$link['page_id'])
                    ->order_by('order')
                    ->get('pages')
                    ->result_array();
                
                if (!empty($children))
                {
                    for ($i=0;$i<sizeof($children);$i++)
                    {
                        $id++;
                        // we need to avoid duplicate entries, so if page_id is already in array, simply ignore it.
                            $indexed_links[$children[$i]['id']] = array(
                                'id'=>(string) $children[$i]['id'],
                                'title'=>$children[$i]['title'],
                                'parent'=>(string) $link['id'],
                                'link_type'=>'page',
                                'page_id'=>$children[$i]['id'],
                                'module_name'=>'',
                                'url'=>'',
                                'uri'=>'',
                                'navigation_group_id'=>$link['navigation_group_id'],
                                'position'=>$children[$i]['order'],
                                'target'=>NULL,
                                'restricted_to'=>NULL,
                                'class'=>'',
                            );
                    }
                }
            }
        }

		$this->load->helper('url');

		$links = array();
		
		// we must reindex the array first and build urls
		// réindex les positions et donc les clé, ce qui fou la merde...
		$all_links = $this->navigation_m->make_url_array($all_links, $user_group, $front_end);
		
		foreach ($indexed_links AS $row)
		{
            //($row['id'] == 0) ? $page_id = 999999 : $page_id = $row['id'];
			$links[$row['id']] = $row;
		}
		unset($indexed_links);

		$link_array = array();

		// build a multidimensional array of parent > children
		foreach ($links AS $row)
		{
			if ( $row['parent'] != 0 && isset($row['parent'], $links) )
			{
				$links[$row['parent']]['children'][] =& $links[$row['id']];
			}
			
			if ( ! isset($links[$row['id']]['children']))
			{
				$links[$row['id']]['children'] = array();
			}

			// this is a root link
			if ($row['parent'] == 0)
			{
				$link_array[] =& $links[$row['id']];
			}
		}
		$link_array = $this->navigation_m->make_url_array($link_array, $user_group, $front_end);
		
		// also had to replace
		// if ( $link['children'] )
		// by if ( isset($link['children']) )
		// in /system/cms/modules/navigation/plugin.php, line 275

		return $link_array;
	}
}