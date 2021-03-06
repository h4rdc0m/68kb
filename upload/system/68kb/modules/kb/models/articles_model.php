<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 68KB
 *
 * An open source knowledge base script
 *
 * @package		68kb
 * @author		Eric Barnes (http://ericlbarnes.com)
 * @copyright	Copyright (c) 2009, 68 Designs, LLC
 * @license		http://68kb.com/user_guide/license.html
 * @link		http://68kb.com
 * @since		Version 1.0
 */

// ------------------------------------------------------------------------

/**
 * Article Model
 *
 * This class is used to handle the article data.
 *
 * @package		68kb
 * @subpackage	Models
 * @category	Models
 * @author		Eric Barnes (http://ericlbarnes.com)
 * @link		http://68kb.com/user_guide/overview/articles.html
 * @version 	$Id: article_model.php 89 2009-08-13 01:54:20Z suzkaw68 $
 */
class Articles_model extends CI_model
{
	/**
	 * Constructor
	 *
	 * @return 	void
	 */
	function __construct()
	{
		parent::__construct();
		log_message('debug', 'Article Model Initialized');
	}

	// ------------------------------------------------------------------------

	/**
	* Insert Article2Cats
	*
	* Insert the selected categories
	* into the article2cat table.
	*
	* @access	public
	* @param	int - The article id
	* @param	array - The array of cats.
	* @return 	bool
	*/
	function insert_cats($cats, $id)
	{
		if (is_array($cats) && is_numeric($id))
		{
			// Delete all associations first
			$this->db->delete('article2cat', array('article_id_rel' => $id));
			foreach ($cats as $cat)
			{
				$data = array(
						'article_id_rel' => (int) $id,
						'category_id_rel' => (int) $cat
					);
				$this->db->insert('article2cat', $data);
			}
			return TRUE;
		}
		return FALSE;
	}

	// ------------------------------------------------------------------------

	/**
	 * Get Category By Article.
	 *
	 * Get a list of categories an article is associated with.
	 *
	 * @access	public
	 * @param	int	the unique id
	 * @return	array
	 */
	function get_cats_by_article($id)
	{
		$this->db->from('article2cat');
		$this->db->join('categories', 'category_id_rel = cat_id', 'left');
		$this->db->where('article_id_rel', (int)$id);
		$this->db->where('cat_display', 'yes');
		$query = $this->db->get();
		return $query;
	}

	// ------------------------------------------------------------------------

	/**
 	* Get Product to Categories
 	*
	* @param	int
 	* @return 	bool
 	*/
	public function get_category_relationship($article_id = '')
	{
		if( ! is_numeric($article_id))
		{
			return FALSE;
		}

		$this->db->select('category_id_rel')->from('article2cat')->where('article_id_rel', (int) $article_id);
		$query = $this->db->get();

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		foreach ($query->result_array() as $row)
		{
			$arr[] = $row['category_id_rel'];
		}

		return $arr;
	}

	// ------------------------------------------------------------------------

	/**
	* Delete Article
	*
	* Responsible for deleting an article.
	*
	* @param	int $cat_id The id of the category to delete.
	* @uses		delete_article_attachments
	* @uses 	delete_article_tags
	* @return	TRUE on success.
	*/
	function delete_article($article_id)
	{
		$article_id=(int)trim($article_id);
		$this->core_events->trigger('articles/delete', $article_id);
		$this->delete_article_attachments($article_id);
		$this->delete_article_tags($article_id);
		$this->db->delete('articles', array('article_id' => $article_id));
		if ($this->db->affected_rows() > 0)
		{
			$this->db->cache_delete_all();
			return TRUE;
		}
		else
		{
			return FALSE;
		}
	}

	// ------------------------------------------------------------------------

	/**
	* Delete all Uploaded files.
	*
	* @access	public
	* @param	int
	*/
	function delete_article_attachments($id)
	{
		$id = (int)$id;
		$this->load->helper('file');
		$this->db->select('attach_id, article_id, attach_name')->from('attachments')->where('article_id', $id);
		$query = $this->db->get();
		if ($query->num_rows() > 0)
		{
			foreach ($query->result() as $row)
			{
				$article_id = $row->article_id;
				@unlink(KBPATH .'uploads/'.$row->article_id.'/'.$row->attach_name);
				$this->db->delete('attachments', array('attach_id' => $id));
			}
		}
	}

	// ------------------------------------------------------------------------

	/**
	* Delete all article tags.
	*
	* @access	public
	* @param	int
	*/
	function delete_article_tags($id)
	{
		$id = (int)$id;
		$this->db->delete('article_tags', array('tags_article_id' => $id));
	}

	// ------------------------------------------------------------------------

	/**
 	* Edit Category
	*
	* Handles editing an article.
 	*
 	* @param	array 	$data An array of data.
	* @uses 	format_uri
 	* @return	bool	TRUE on success.
 	*/
	function edit_article($article_id, $data)
	{
		$article_id = (int) $article_id;

		if (isset($data['article_uri']) && $data['article_uri'] != '')
		{
			$data['article_uri'] = create_slug($data['article_uri']);
		}
		else
		{
			$data['article_uri'] = create_slug($data['article_title']);
		}

		if ( ! isset($data['article_modified']) )
		{
			$data['article_modified'] = time();
		}

		$this->db->where('article_id', $article_id);
		$this->db->update('articles', $data);

		if ($this->db->affected_rows() > 0)
		{
			$this->events->trigger('articles/edit', $article_id);
			return TRUE;
		}
		else
		{
			log_message('info', 'Could not edit the post id '. $article_id);
			return FALSE;
		}
	}

	// ------------------------------------------------------------------------

	/**
 	* Add Article
	*
	* Add an article to the db.
 	*
 	* @param	array 	$data An array of data.
	* @uses 	format_uri
 	* @return	mixed 	Id on success.
 	*/
	function add_article($data)
	{
		if (isset($data['article_uri']) && $data['article_uri'] != '')
		{
			$data['article_uri'] = create_slug($data['article_uri']);
		}
		else
		{
			$data['article_uri'] = create_slug($data['article_title']);
		}

		//update the time stamps.
		$data['article_date'] = time();
		$data['article_modified'] = time();

		$this->db->insert('articles', $data);

		if($this->db->affected_rows() > 0)
		{
			$article_id = $this->db->insert_id();
			$this->events->trigger('articles/add', $article_id);
			return $article_id;
		}
		else
		{
			return FALSE;
		}
	}

	// ------------------------------------------------------------------------

	/**
	* Check URI
	*
	* Checks other articles for the same uri.
	*
	* @param	string $article_uri The uri name
	* @return	boolean True if checks out ok, FALSE otherwise
	*/
	function check_uri($article_uri, $article_id=FALSE)
	{
		if ($article_id !== FALSE)
		{
			$article_id=(int)$article_id;
			$this->db->select('article_uri')->from('articles')->where('article_uri', $article_uri)->where('article_id !=', $article_id);
		}
		else
		{
			$this->db->select('article_uri')->from('articles')->where('article_uri', $article_uri);
		}
		$query = $this->db->get();
		if ($query->num_rows() > 0)
		{
			return FALSE;
		}
		else
		{
			return TRUE;
		}
	}

	// ------------------------------------------------------------------------

	/**
	* Get Article
	*
	* Gets a list of all articles
	*
	* @param	int The limit
	* @param	int The offset
	* @param	bool Should you count only?
	* @return	mixed Either int or array.
	*/
	function get_articles()
	{
		$this->db->from('articles')->where('article_display', 'y')->order_by('article_order DESC, article_title ASC');
		$query = $this->db->get();
		return $query;
	}

	// ------------------------------------------------------------------------

	/**
	 * Get Article By Cat ID.
	 *
	 * Get a list of articles from the
	 * same category.
	 *
	 * @access	public
	 * @param	int	the category id
	 * @param	int Limit
	 * @param	int Current Row
	 * @param	bool
	 * @return	mixed
	 */
	function get_articles_by_catid($id, $limit = 0, $current_row = 0, $show_count = TRUE)
	{
		$id = (int)$id;
		$this->db->from('articles');
		$this->db->join('article2cat', 'article_id = article_id_rel', 'left');
		$this->db->where('category_id_rel', $id);
		$this->db->where('article_display', 'y');
		if ($show_count)
		{
			return $this->db->count_all_results();
		}
		if ($limit > 0)
		{
			$this->db->limit($limit, $current_row);
		}
		$query = $this->db->get();

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		$data = $query->result_array();

		$query->free_result();

		return $data;
	}

	// ------------------------------------------------------------------------

	/**
	 * Get Article By URI.
	 *
	 * Get a single article from its a_uri
	 *
	 * @access	public
	 * @param	string	the unique uri
	 * @return	array
	 */
	function get_article_by_uri($uri)
	{
		$this->db->from('articles')->where('article_uri', $uri)->where('article_display', 'Y');
		$query = $this->db->get();
		if ($query->num_rows > 0)
		{
			$data = $query->row_array();
			$query->free_result();
			return  $data;
		}
		else
		{
			return FALSE;
		}
	}

	// ------------------------------------------------------------------------

	/**
	* Get Article
	*
	* @param	mixed
	* @uses		_get_article_by_id
	* @uses		_get_listing_by_uri
	*/
	public function get_article($query)
	{
		if (is_numeric($query))
		{
			return $this->_get_article_by_id($query);
		}
		else
		{
			return $this->_get_listing_by_query($query);
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Get Article By ID.
	 *
	 * Get a single article from its ID
	 *
	 * @access	public
	 * @param	int	the id
	 * @return	array
	 */
	private function _get_article_by_id($id)
	{
		$id = (int) $id;

		$this->db->from('articles')
				->join('users', 'article_author = user_id', 'left')
				->where('article_id', $id);

		$query = $this->db->get();

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		$data = $query->row_array();

		$query->free_result();

		return $data;
	}

	// ------------------------------------------------------------------------

	/**
	 * Get Attachments
	 *
	 * Get all attachments by article ID
	 *
	 * @access	public
	 * @param	int	the id
	 * @return	array
	 */
	function get_attachments($id)
	{
		$id = (int)$id;

		$this->db->from('attachments')->where('article_id', $id);

		$query = $this->db->get();

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		$data = $query->result_array();

		$i = 0;
		foreach ($data as $item)
		{
			$data[$i]['download_path'] = 'uploads/'.$id.'/'.$item['attach_file'];
			$i++;
		}
		$query->free_result();

		return $data;
	}

	// ------------------------------------------------------------------------

	/**
	 * Get Most Popular.
	 *
	 * Get a list of the most popular articles.
	 *
	 * @access	public
	 * @param	int	the number to return
	 * @return	array
	 */
	function get_most_popular($number=25)
	{
		$number = (int)$number;
		$this->db->select('article_uri,article_title')
					->from('articles')
					->where('article_display', 'Y')
					->orderby('article_hits', 'DESC')
					->limit($number);
		$query = $this->db->get();
		return $query;
	}

	// ------------------------------------------------------------------------

	/**
	 * Get Highest Rated.
	 *
	 * Get a list of the articles and order by rating.
	 * Usuage:
	 * <code>
	 * $this->article_model->get_highest_rated(10);
	 * </code>
	 *
	 * @access	public
	 * @param	int	the number to return
	 * @return	array
	 */
	function get_highest_rated($number=25)
	{
		$number = (int)$number;
		$this->db->select('article_uri,article_title')
					->from('articles')
					->where('article_display', 'Y')
					->orderby('article_rating', 'DESC')
					->limit($number);
		$query = $this->db->get();
		return $query;
	}

	// ------------------------------------------------------------------------

	/**
	 * Get Latest Articles.
	 *
	 * Get a list of the latest articles
	 *
	 * @access	public
	 * @param	int	the number to return
	 * @return	array
	 */
	function get_latest($number=25)
	{
		$number = (int)$number;
		$this->db->from('articles')
				->where('article_display', 'y')
				->orderby('article_date', 'DESC')
				->limit($number);

		$query = $this->db->get();

		if ($query->num_rows() == 0)
		{
			return FALSE;
		}

		$data = $query->result_array();

		$query->free_result();

		return $data;
	}

	// ------------------------------------------------------------------------

	/**
	 * Get Related Articles.
	 *
	 * Get a list of the latest articles
	 *
	 * @access	public
	 * @param	int	the number to return
	 * @return	array
	 */
	function get_related($id, $number=5)
	{
		$id = (int)$id;
		$number = (int)$number;

		$related = array();

		$this->db->select('tags_tag_id,tag');
		$this->db->join('tags', 'id = tags_tag_id', 'inner');
		$this->db->where('tags_article_id', $id);
		$query = $this->db->get('article_tags');

		$i = 0;

		if ($query->num_rows > 0)
		{
			$result = $query->result_array();

			foreach ($result as $row)
			{
				$this->db->where('tags_tag_id', $row['tags_tag_id']);
				$this->db->where('tags_article_id !=', $id);
				$query2 = $this->db->get('article_tags');
				if($query2->num_rows > 0 && $i < $number)
				{
					$result = $query2->result_array();
					foreach($result as $rs)
					{
						$related[]=$rs;
						$i++;
					}
				}

			}
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Add Hit
	 *
	 * Increase the article hit count.
	 *
	 * @access	public
	 * @param	int	the article id.
	 * @return	bool
	 */
	function add_hit($id)
	{
		$id=(int)$id;
		$this->db->select('article_hits')->from('articles')->where('article_id', $id);
		$query = $this->db->get();
		if ($query->num_rows() > 0)
		{
			$row = $query->row();
			$hits = $row->article_hits+1;
			$data = array('article_hits' => $hits);
			$this->db->where('article_id', $id);
			$this->db->update('articles', $data);
			return TRUE;
		}
		return FALSE;
	}

	// ------------------------------------------------------------------------

	/**
	 * Glossary
	 *
	 * Checks for terms in the glossary and links the text to the term. Also
	 * can be used in conjunction with jQuery tooltip.
	 *
	 * @link 	http://bassistance.de/jquery-plugins/jquery-plugin-tooltip/
	 * @uses	_dot
	 * @param	string	the content
	 * @return	string 	the content with the link
	 */
	function glossary($content)
	{
		$this->db->select('g_id,g_term,g_definition')->from('glossary');
		$query = $this->db->get();
		if ($query->num_rows() > 0)
		{
			foreach ($query->result() as $row)
			{
				$pos = strpos(strtolower($content), strtolower($row->g_term));
				if ($pos !== FALSE)
				{
					$definition = $this->_dot($row->g_definition,75);
					$definition = str_replace('"', '\'', $definition);

					// Fix per http://github.com/68designs/68KB/issues#issue/6
					$html_stuff = array("<p>", "</p>");
					$definition = str_replace($html_stuff, "", $definition);

					$replacement = ' <a href="'.site_url('glossary/term/'.$row->g_term).'" class="tooltip" title="'.$row->g_term.' - '.$definition.'">'.$row->g_term.'</a> ';
					$content = preg_replace('/[\b|\s]('.$row->g_term.')[\b|^\s]/i', $replacement, $content, 1);
				}
			}
		}
		return $content;
	}

	// ------------------------------------------------------------------------

	/**
	 * dot
	 *
	 * Trims a string and adds periods to the end
	 *
	 * @access	private
	 * @param	string	the string
	 * @param	int		the length
	 * @param	string	the ending value
	 * @return	string 	the trimmed string
	 */
	private function _dot($str, $len, $dots = "...")
	{
		if (strlen($str) > $len)
		{
			$dotlen = strlen($dots);
			$str = substr_replace($str, $dots, $len - $dotlen);
		}
		return $str;
	}

	// ------------------------------------------------------------------------

	/**
	 * Add Listing Fields
	 *
	 * @param	array
	 * @return	mixed
	 */
	public function add_fields($data)
	{
		$this->db->insert('article_fields', $data);

		if ($this->db->affected_rows() == 0)
		{
			return FALSE;
		}

		$field_id = $this->db->insert_id();

		$this->events->trigger('articles_model/add_fields', $field_id);

		return $field_id;
	}
}

/* End of file article_model.php */
/* Location: ./upload/includes/application/models/article_model.php */