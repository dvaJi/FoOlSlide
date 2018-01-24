<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class V1 extends REST_Controller
{
	/**
	 * Returns 100 comics from selected page
	 *
	 * Available filters: page, per_page (default:30, max:100), orderby
	 *
	 * @author Woxxy
	 */
	function comics_get()
	{
		$comics = new Comic();

		// filter with orderby
		$this->_orderby($comics);
		// use page_to_offset function
		$this->_page_to_offset($comics);

		$lang = $this->get('lang');

		$comics->get();

		if ($comics->result_count() > 0)
		{
			$result = array();
			$index = 0;
			foreach ($comics->all as $key => $comic)
			{
				$isAvailable = FALSE;
				$description = "";

				$descriptions = new Description();
				$descriptions->where('comic_id', $comic->id)->get();
				if ($descriptions->result_count() > 0) {
					foreach ($descriptions->all as $keydesc => $desc) {
						if ($desc->language == $lang) {
							$isAvailable = TRUE;
							$description = $desc->to_array()['description'];
						}
					}
				}

				if ($isAvailable) {
					$result[$index] = $comic->to_array();
					$result[$index]['description'] = $description;
					$comicDir = "content/comics/" . $comic->stub . "_" . $comic->uniqid . "/";

					$image_path = $comicDir . $comic->thumbnail;
					$image_thumb = $comicDir . "thumb2_" . $comic->thumbnail;
					if ( !file_exists( $image_thumb ) ) {
						// LOAD LIBRARY
						$this->load->library( 'image_lib' );

						// CONFIGURE IMAGE LIBRARY
						$config['image_library']    = 'gd2';
						$config['source_image']     = $image_path;
						$config['new_image']        = $image_thumb;
						$config['maintain_ratio']   = TRUE;
						$config['height']           = 390;
						$config['width']            = 300;
						$this->image_lib->initialize( $config );
						$this->image_lib->resize();
						$this->image_lib->clear();
					}

					$result[$index]['thumb2'] = $image_thumb;
					$index++;
				}

			}
			$this->response($result, 200); // 200 being the HTTP response code
		}
		else
		{
			// no comics
			$this->response(array('error' => _('Comics could not be found')), 404);
		}
	}

	/**
	 * Returns the comic
	 *
	 * Available filters: id (required)
	 *
	 * @author Woxxy
	 */
	function comic_get()
	{
		if ($this->get('id'))
		{
			//// check that the id is at least a valid number
			$this->_check_id();

			// get the comic
			$comic = new Comic();
			$comic->where('id', $this->get('id'))->limit(1)->get();
		}
		else if ($this->get('stub'))
		{ // mostly used for load balancer
			$comic = new Comic();
			$comic->where('stub', $this->get('stub'));
			// back compatibility with version 0.7.6, though stub is already an unique key
			if ($this->get('uniqid'))
				$comic->where('uniqid', $this->get('uniqid'));
			$comic->limit(1)->get();
		}
		else
		{
			$this->response(array('error' => _('You didn\'t use the necessary parameters')), 404);
		}

		if ($comic->result_count() == 1)
		{
			$chapters = new Chapter();
			$chapters->where('comic_id', $comic->id)->get();
			$chapters->get_teams();
			$result = array();

			$result = $comic->to_array();
			$descriptions = new Description();
			$descriptions->where('comic_id', $comic->id)->get();
			foreach ($descriptions->all as $key => $desc) {
				if ($desc->language == $this->get('lang')) {
					$result['description'] = $desc->to_array()['description'];
				}
				$result['descriptions'][$key] = $desc->to_array();
				$result['languages'][$key] = $desc->language;
			}

			$comicDir = "content/comics/" . $comic->stub . "_" . $comic->uniqid . "/";

			$image_path = $comicDir . $comic->thumbnail;
			$image_thumb = $comicDir . "thumb2_" . $comic->thumbnail;
			if ( !file_exists( $image_thumb ) ) {
				// LOAD LIBRARY
				$this->load->library( 'image_lib' );

				// CONFIGURE IMAGE LIBRARY
				$config['image_library']    = 'gd2';
				$config['source_image']     = $image_path;
				$config['new_image']        = $image_thumb;
				$config['maintain_ratio']   = TRUE;
				$config['height']           = 390;
				$config['width']            = 300;
				$this->image_lib->initialize( $config );
				$this->image_lib->resize();
				$this->image_lib->clear();
			}

			$result['thumb2'] = site_url() . $image_thumb;

			// order in the beautiful [comic][chapter][teams][page]
			$result["chapters"] = array();
			$chaptersIndex = 0;
			foreach ($chapters->all as $key => $chapter)
			{
				if ($chapter->language == $this->get('lang')) {
					$result['chapters'][$chaptersIndex] = $chapter->to_array();
					$subchapter = 0;
					if($this->get('subchapter') == $chapter->subchapter){
						$subchapter = $this->get('subchapter');
					}
					if ($this->get('chapter') == $chapter->chapter && $chapter->subchapter == $subchapter)
					{

						$pages = new Page();
						$pages->where('chapter_id', $chapter->id)->get();
						$result["chapters"][$chaptersIndex]["chapter"]["pages"] = $chapter->get_pages();
					}
					$chaptersIndex++;
				}
			}

			// all good
			$this->response($result, 200); // 200 being the HTTP response code
		}
		else
		{
			// there's no comic with that id
			$this->response(array('error' => _('Comic could not be found')), 404);
		}
	}

	/**
	* chapters+pages+comic_get
	*/
	function releases_get() {
		$chapters = new Chapter();

		// get the generic chapters and the comic coming with them
		if ($this->get('lang')) {
			$chapters->where('language', $this->get('lang'));
		}

		// filter with orderby
		$this->_orderby($chapters);
		// use page_to_offset function
		$this->_page_to_offset($chapters);



		$chapters->get();
		$chapters->get_comic();

		if ($chapters->result_count() > 0) {

			// let's create a pretty array of chapters [comic][chapter][teams]
			$result['chapters'] = array();
			foreach ($chapters->all as $key => $chapter) {
				$result['chapters'][$key]['comic'] = $chapter->comic->to_array();
				$result['chapters'][$key]['chapter'] = $chapter->to_array();

				// TODO: SOLO OBTENER LA PÁGINA NECESARIA.
				$pages = $chapter->get_pages();
				//$result['chapters'][$key]['pages'] = $chapter->get_pages();

				$comicDir = "content/comics/" . $chapter->comic->stub . "_" . $chapter->comic->uniqid . "/";

				$image_path = $comicDir . $chapter->stub . "_" . $chapter->uniqid . "/" . $pages[2]['filename'];
				$image_thumb = $comicDir . $chapter->stub . "_" . $chapter->uniqid . "/thumb_" . $pages[2]['filename'];

				if ( !file_exists( $image_thumb ) ) {
					// LOAD LIBRARY
					$this->load->library( 'image_lib' );

					// CONFIGURE IMAGE LIBRARY
					$config['image_library']    = 'gd2';
					$config['source_image']     = $image_path;
					$config['new_image']        = $image_thumb;
					$config['maintain_ratio']   = TRUE;
					$config['height']           = 390;
					$config['width']            = 300;
					$this->image_lib->initialize( $config );
					$this->image_lib->resize();
					$this->image_lib->clear();
				}

				$result['chapters'][$key]['chapter']['thumbnail'] = $image_thumb;
				//$result['chapters'][$key]['chapter']['thumbnail'] = 'api/' . $image_thumb;
				$result['chapters'][$key]['id'] = $chapter->id;
				$result['chapters'][$key]['loading'] = false;

				$chapter->get_teams();
				foreach ($chapter->teams as $item) {
					$result['chapters'][$key]['teams'][] = $item->to_array();
				}
			}

			// all good
			$this->response($result['chapters'], 200); // 200 being the HTTP response code
		}
		else {
			// no comics
			$this->response(array('error' => _('Comics could not be found')), 404);
		}
	}

	/**
	 * Returns chapters from selected comic
	 *
	 *
	 * @author Woxxy
	 */
	function chapters_get()
	{
		if ($this->get('stub')) {
			$chapter = new Chapter();

			// filter with orderby
			$this->_orderby($chapter);
			// use page_to_offset function
			$this->_page_to_offset($chapter);

			$chapter->where_related('comic', 'stub', $this->get('stub'));
			$chapter->get()->to_array();
		} else {
			$chapter = array();
		}
		if ($chapter->result_count() > 0) {

			$result = array();
			$chapter->get_comic();
			foreach ($chapter->all as $key => $chapter) {
				$result[$key] = $chapter->to_array();
				$result[$key]['comic'] = $chapter->comic->to_array();
				$result[$key]['pages'] = $chapter->get_pages();
			}

			// all good
			$this->response($result, 200); // 200 being the HTTP response code
		}
		else
		{
			// the chapter with that id doesn't exist
			$this->response(array('error' => _('Chapter could not be found')), 404);
		}
	}


	/**
	 * Returns the chapter
	 *
	 * Available filters: id (required)
	 *
	 * @author Woxxy
	 */
	function chapter_get()
	{
		if (	($this->get('comic_stub'))
				|| is_numeric($this->get('comic_id'))
				|| is_numeric($this->get('volume'))
				|| is_numeric($this->get('chapter'))
				|| is_numeric($this->get('subchapter'))
				|| is_numeric($this->get('team_id'))
				|| is_numeric($this->get('joint_id'))
		)
		{
			$chapter = new Chapter();

			if(($this->get('comic_stub')))
			{
				$chapter->where_related('comic', 'stub', $this->get('comic_stub'));
			}

			// this mess is a complete search system through integers!
			if (is_numeric($this->get('comic_id')))
				$chapter->where('comic_id', $this->get('comic_id'));
			if (is_numeric($this->get('volume')))
				$chapter->where('volume', $this->get('volume'));
			if (is_numeric($this->get('chapter')))
				$chapter->where('chapter', $this->get('chapter'));
			if (is_numeric($this->get('subchapter')))
				$chapter->where('subchapter', $this->get('subchapter'));
			if (is_numeric($this->get('team_id')))
				$chapter->where('team_id', $this->get('team_id'));
			if (is_numeric($this->get('joint_id')))
				$chapter->where('joint_id', $this->get('joint_id'));

			// and we'll still give only one result
			$chapter->limit(1)->get();
		}
		else
		{
			// check that the id is at least a valid number
			$this->_check_id();

			$chapter = new Chapter();
			// get the single chapter by id
			$chapter->where('id', $this->get('id'))->limit(1)->get();
		}


		if ($chapter->result_count() == 1)
		{
			$chapter->get_comic();
			$chapter->get_teams();

			// the pretty array gets pages too: [comic][chapter][teams][pages]
			$result = array();
			//$result['comic'] = $chapter->comic->to_array();
			$result['chapter'] = $chapter->to_array();
			$result['teams'] = array();
			foreach ($chapter->teams as $team)
			{
				$result['teams'][] = $team->to_array();
			}

			// this time we get the pages
			$result['pages'] = $chapter->get_pages();

			// all good
			$this->response($result, 200); // 200 being the HTTP response code
		}
		else
		{
			// the chapter with that id doesn't exist
			$this->response(array('error' => _('Chapter could not be found')), 404);
		}
	}


	/**
	 * Returns chapters per page from team ID
	 * Includes releases from joints too
	 *
	 * This is NOT a method light enough to lookup teams. use api/members/team for that
	 *
	 * Available filters: id (required), page, per_page (default:30, max:100), orderby
	 *
	 * @author Woxxy
	 */
	function team_get()
	{
		// get the single team by id or stub
		if ($this->get('stub'))
		{
			$team = new Team();
			$team->where('stub', $this->get('stub'))->limit(1)->get();
		}
		else
		{
			// check that the id is at least a valid number
			$this->_check_id();
			$team = new Team();
			$team->where('id', $this->get('id'))->limit(1)->get();
		}

		// team found?
		if ($team->result_count() == 1)
		{
			$result = array();
			$result['team'] = $team->to_array();

			// get joints to get also the chapters from joints
			$joints = new Joint();
			$joints->where('team_id', $team->id)->get();


			$chapters = new Chapter();
			// get all chapters with the team ID
			$chapters->where('team_id', $team->id);
			foreach ($joints->all as $joint)
			{
				// get also all chapters with the joints by the team
				$chapters->or_where('joint_id', $joint->joint_id);
			}

			// filter for the page and the order
			$this->_orderby($chapters);
			$this->_page_to_offset($chapters);
			$chapters->get();
			$chapters->get_comic();

			// let's save some power by reusing the variables we already have for team
			// and put everything in the usual clean [comic][chapter][teams]
			$result['chapters'] = array();
			foreach ($chapters->all as $key => $chapter)
			{
				if (!$chapter->team_id)
				{
					$chapter->get_teams();
					foreach ($chapter->teams as $item)
					{
						$result['chapters'][$key]['teams'][] = $item->to_array();
					}
				}
				else
				{
					$result['chapters'][$key]['teams'][] = $team->to_array();
				}
				$result['chapters'][$key]['comic'] = $chapter->comic->to_array();
				$result['chapters'][$key]['chapter'] = $chapter->to_array();
			}

			// all good
			$this->response($result, 200); // 200 being the HTTP response code
		}
		else
		{
			// that single team id wasn't found
			$this->response(array('error' => _('Team could not be found')), 404);
		}
	}


	/**
	 * Returns chapters per page by joint ID
	 * Also returns the teams
	 *
	 * This is not a method light enough to lookup teams. use api/members/joint for that
	 *
	 * Available filters: id (required), page, per_page (default:30, max:100), orderby
	 *
	 * @author Woxxy
	 */
	function joint_get()
	{
		// check that the id is at least a valid number
		$this->_check_id();

		// grab by joint_id, id for joints means nothing much
		$joint = new Joint();
		$joint->where('joint_id', $this->get('id'))->limit(1)->get();

		if ($joint->result_count() == 1)
		{
			// good old get_teams() will give us all Team objects in an array
			$team = new Team();
			$teams = $team->get_teams(0, $this->get('id'));

			// $teams is a normal array, so we have to do a loop
			$result = array();
			foreach ($teams as $item)
			{
				$result['teams'][] = $item->to_array();
			}

			// grab all the chapters from the same joint
			$chapters = new Chapter();
			$chapters->where('joint_id', $joint->joint_id);

			// apply the limit and orderby filters
			$this->_orderby($chapters);
			$this->_page_to_offset($chapters);
			$chapters->get();
			$chapters->get_comic();

			// let's put the chapters in a nice [comic][chapter][teams] list
			$result['chapters'] = array();
			foreach ($chapters->all as $key => $chapter)
			{
				$result['chapters'][$key]['comic'] = $chapter->comic->to_array();
				$result['chapters'][$key]['chapter'] = $chapter->to_array();
				$result['chapters'][$key]['teams'] = $result['teams'];
			}

			// all good
			$this->response($result, 200); // 200 being the HTTP response code
		}
		else
		{
			// nothing for this joint or page
			$this->response(array('error' => _('Team could not be found')), 404);
		}
	}

	/**
	 * Returns all post and seleccted one by id and stub
	 *
	 * Available filters: page, per_page (default:30, max:100), orderby
	 *
	 * @author dvaJi
	 */
	function blog_get() {
		if ($this->get('id')) {
			$this->_check_id();
			$post = new Post();
			$post->where('id', $this->get('id'))->limit(1)->get();
			$post->get_users();

		} else if ($this->get('stub')) {
			$post = new Post();
			$post->where('stub', $this->get('stub'));
			$post->limit(1)->get();
			$post->get_users();

		} else {
			$posts = new Post();
			if ($this->get('lang')) {
				$posts->where('language', $this->get('lang'));
			}
			// filter with orderby
			$this->_orderby($posts);
			// use page_to_offset function
			$this->_page_to_offset($posts);
			$posts->get();
			$posts->get_users();
		}

		if ($post != null && $post->result_count() == 1) {
			$result["data"]["post"] = $post->to_array();
			$this->response($result, 200); // 200 being the HTTP response code
		} else if ($posts != null && $posts->result_count() > 0) {
			$result = array();
			$result['data'] = array();
			foreach ($posts->all as $key => $post)
			{
				$result['data'][$key] = $post->to_array();
			}

			$this->response($result, 200); // 200 being the HTTP response code
		} else {
			// there's no post with that id
			$this->response(array('error' => _('Post could not be found')), 404);
		}
	}

	/**
	 * Returns all custompage and seleccted one by id and stub
	 *
	 * Available filters: page, per_page (default:30, max:100), orderby
	 *
	 * @author dvaJi
	 */
	function pages_get() {
		if ($this->get('id')) {
			$this->_check_id();
			$custompage = new Custompage();
			$custompage->where('id', $this->get('id'))->limit(1)->get();
			$custompage->get_users();

		} else if ($this->get('stub')) {
			$custompage = new custompage();
			$custompage->where('stub', $this->get('stub'));
			$custompage->limit(1)->get();
			$custompage->get_users();

		} else {
			$custompages = new custompage();
			if ($this->get('lang')) {
				$custompages->where('language', $this->get('lang'));
			}
			// filter with orderby
			$this->_orderby($custompages);
			// use page_to_offset function
			$this->_page_to_offset($custompages);
			$custompages->get();
		}

		if ($custompage != null && $custompage->result_count() == 1) {
			$result["data"]["custompage"] = $custompage->to_array();
			$this->response($result, 200); // 200 being the HTTP response code
		} else if ($custompages != null && $custompages->result_count() > 0) {
			$result = array();
			$result['data'] = array();
			foreach ($custompages->all as $key => $custompage)
			{
				// We only need those elements
				$result['data'][$key]['title'] = $custompage->title();
				$result['data'][$key]['stub'] = $custompage->stub;
			}

			$this->response($result, 200); // 200 being the HTTP response code
		} else {
			// there's no custompage with that id
			$this->response(array('error' => _('custompage could not be found')), 404);
		}
	}


}
