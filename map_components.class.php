<?php
/* Map Component by MinSoo Kim. (c) 2014-2015 MinSoo Kim. (misol.kr@gmail.com) */
class map_components extends EditorHandler {
	var $editor_sequence = '0';
	var $component_path = '';
	var $mobile_set = false;
	var $maps_api_type = '';

	var $langtype = '';
		//language setting
	var $xe_langtype = array(
			'ko',
			'en',
			'zh-tw',
			'zh-cn',
			'jp',
			'es',
			'fr',
			'ru',
			'vi',
			'mn',
			'tr'
		);
	var $google_langtype = array(
			'ko',
			'en',
			'zh-Hant',
			'zh-Hans',
			'ja',
			'es',
			'fr',
			'ru',
			'vi',
			'en', // google does not not support
			'tr'
		);

	/**
	 * @brief editor_sequence과 컴포넌트의 경로를 받음
	 **/
	function __construct($editor_sequence, $component_path) {
		$this->editor_sequence = $editor_sequence;
		$this->component_path = $component_path;
		Context::loadLang($component_path.'lang');
		if(Mobile::isFromMobilePhone()) {
			$this->mobile_set = true;
		}
		$this->langtype = str_replace($this->xe_langtype, $this->google_langtype, strtolower(Context::getLangType()));
	}

	private function getApiHost($api_key) {
		// API 종류 정하기 다음/네이버/구글
		if(trim($api_key))
		{
			if($api_key === $this->soo_daum_local_api_key || strlen($api_key) === 40 || (trim($this->soo_map_api_type) === 'daum' && strlen($api_key) == 32))
			{
				if(!$this->soo_daum_local_api_key && strlen($api_key) === 40)
				{
					$this->soo_daum_local_api_key = $api_key;
				}
				elseif(trim($this->soo_map_api_type) === 'daum' && !$this->soo_daum_local_api_key && strlen($api_key) == 32)
				{
					$this->soo_daum_local_api_key = $api_key;
				}
				$this->maps_api_type = 'daum';
			}
			elseif(strlen($api_key) == 32 || strlen($api_key) == 20 || strlen($api_key) == 10 || trim($this->soo_map_api_type) === 'naver_cp' || trim($this->soo_map_api_type) === 'naver_gov')
			{
				$this->maps_api_type = 'naver';
			}
			else
			{
				$this->maps_api_type = 'google';
			}
		}
		else
			$this->maps_api_type = 'leaflet';

		return $this->maps_api_type;
	}

	function encode_data() {
		$data = Context::gets('map_center', 'width', 'height', 'map_markers', 'map_zoom');
		if(!$data) return;
		$data = base64_encode(serialize($data));

		$this->add("maps_key", $this->soo_map_api);
		$this->add("results", $data);
	}

	function decode_data() {
		$data = Context::get('data');
		if(!$data) return;
		$data = unserialize(base64_decode($data));

		$style = trim(Context::get('style')).';';
		preg_match('/width([ ]*)\:([0-9 a-z\.]+)\;/i', $style, $width_style);
		preg_match('/height([ ]*)\:([0-9 a-z\.]+)\;/i', $style, $height_style);
		if($width_style[2]) $width = intval(trim($width_style[2]));
		if(!$width) $width = intval($xml_obj->attrs->width);
		if(!$width) {$width = 600;}

		if($height_style[2]) $height = intval(trim($height_style[2]));
		if(!$height) $height = intval($xml_obj->attrs->height);
		if(!$height) {$height = 300;}

		$this->add("width", $width);
		$this->add("height", $height);
		$this->add("results", $data);
	}

	function xml_api_request($uri, $headers = null) {
		$request_config = array(
			'ssl_verify_peer' => FALSE,
			'ssl_verify_host' => FALSE
		);

		$xml = '';
		$xml = FileHandler::getRemoteResource($uri, null, 5, 'GET', 'application/xml', $headers, array(), array(), $request_config);

		$xml = preg_replace("/<\?xml([^>]*)\?>/i", "", $xml);

		$oXmlParser = new XmlParser();
		$xml_doc = $oXmlParser->parse($xml);

		return $xml_doc;
	}

	function json_api_request($uri, $headers = null) {
		$request_config = array(
			'ssl_verify_peer' => FALSE,
			'ssl_verify_host' => FALSE
		);

		$json = '';
		$json = FileHandler::getRemoteResource($uri, null, 5, 'GET', 'application/json', $headers, array(), array(), $request_config);

		$json_doc = json_decode($json);

		return $json_doc;
	}

	function search() {
		$address = Context::get('address');
		if(!$address) return;

		// API 종류 정하기 다음/네이버/구글
		$this->maps_api_type = $this->getApiHost($this->soo_map_api);

		// 구글 장소명-좌표 변환 API
		$uri = sprintf('https://maps.googleapis.com/maps/api/geocode/xml?address=%s&sensor=false&language=%s',urlencode($address),urlencode($this->langtype));
		$xml_doc = $this->xml_api_request($uri);

		$item = $xml_doc->geocoderesponse->result;
		if(!is_array($item)) $item = array($item);
		$item_count = count($item);

		if($item_count > 0) {
			for($i=0;$i<$item_count;$i++) {
				$input_obj = '';
				$input_obj = $item[$i];
				if(!$input_obj->formatted_address->body) continue;
				$result[$i]->formatted_address = $input_obj->formatted_address->body;
				$result[$i]->geometry->lng = $input_obj->geometry->location->lng->body;
				$result[$i]->geometry->lat = $input_obj->geometry->location->lat->body;
				$result[$i]->result_from = 'Google';
			}

		}

		// 네이버 주소-좌표 변환 API. 구글과 달리 Point of interest 검색은 되지 않는다. (예전엔 되더니...)
		if($this->maps_api_type == 'naver') {
			
			if(trim($this->soo_map_api_type) === 'naver_cp' || trim($this->soo_map_api_type) === 'naver_gov')
			{
				$uri = sprintf('https://naveropenapi.apigw.ntruss.com/map-geocode/v2/geocode?query=%s', urlencode($address));
				$header = array(
					'X-NCP-APIGW-API-KEY-ID' => $this->soo_map_api,
					'X-NCP-APIGW-API-KEY' => $this->soo_naver_secret_key
				);
				$json_doc = $this->json_api_request($uri, $header);
				$item = $json_doc->addresses;

				if(!is_array($item)) $item = array($item);
				$item_count = count($item);
				if($item_count > 0) {
					$result_orgin_count = count($result);
					for($i=$result_orgin_count;($i-$result_orgin_count)<$item_count;$i++) {
						$input_obj = '';
						$j = $i-$result_orgin_count;
						$input_obj = $item[$j];
						if(!$input_obj->x) continue;
						$result[$i]->formatted_address = str_replace('  ', ' ', trim($input_obj->roadAddress));
						$result[$i]->geometry->lng = $input_obj->x;
						$result[$i]->geometry->lat = $input_obj->y;
						$result[$i]->result_from = 'Naver';
					}
				}
			}
			else {
				$uri = sprintf('https://openapi.naver.com/v1/map/geocode.xml?query=%s', urlencode($address));
				$header = array(
					'X-Naver-Client-Id' => $this->soo_map_api,
					'X-Naver-Client-Secret' => $this->soo_naver_secret_key
				);
				$xml_doc = $this->xml_api_request($uri, $header);
				$item = $xml_doc->result->items->item;
				
				if(!is_array($item)) $item = array($item);
				$item_count = count($item);
				if($item_count > 0) {
					$result_orgin_count = count($result);
					for($i=$result_orgin_count;($i-$result_orgin_count)<$item_count;$i++) {
						$input_obj = '';
						$j = $i-$result_orgin_count;
						$input_obj = $item[$j];
						if(!$input_obj->address->body) continue;
						$result[$i]->formatted_address = str_replace('  ', ' ', trim($input_obj->address->body));
						$result[$i]->geometry->lng = $input_obj->point->x->body;
						$result[$i]->geometry->lat = $input_obj->point->y->body;
						$result[$i]->result_from = 'Naver';
					}

				}
			}


		}

		// 카카오 장소키워드-좌표 검색
		if($this->soo_daum_local_api_key) {
			$uri = sprintf('https://dapi.kakao.com/v2/local/search/keyword.xml?query=%s',urlencode($address));
			$header = array(
				'Authorization' => 'KakaoAK ' . $this->soo_daum_local_api_key
			);
			$xml_doc = $this->xml_api_request($uri, $header);

			$item = $xml_doc->result->documents;
			if(!is_array($item)) $item = array($item);
			$item_count = count($item);

			if($item_count > 0) {
				$result_orgin_count = count($result);
				for($i=$result_orgin_count;($i-$result_orgin_count)<$item_count;$i++) {
					$input_obj = '';
					$j = $i-$result_orgin_count;
					$input_obj = $item[$j];
					if(!$input_obj->place_name->body) continue;
					$result[$i]->formatted_address = $input_obj->place_name->body;
					$result[$i]->geometry->lng = $input_obj->x->body;
					$result[$i]->geometry->lat = $input_obj->y->body;
					$result[$i]->result_from = 'KAKAO';
				}
			}
		}
		$this->add("results", $result);
	}
	/** @brief popup window요청시 popup window에 출력할 내용을 추가하면 된다**/
	function getPopupContent() {
		// 템플릿을 미리 컴파일해서 컴파일된 소스를 return. Compile the popup contents and return it.
		$tpl_path = $this->component_path.'tpl';
		$tpl_file = 'popup_maps.html';

		// API 종류 정하기 다음/네이버/구글
		$this->maps_api_type = $this->getApiHost($this->soo_map_api);

		// 다음과 네이버는 국내 지도만 사용가능. 구글은 세계지도.
		if($this->maps_api_type == 'daum')
		{
			$this->map_comp_lat = 37.57;
			$this->map_comp_lng = 126.98;

			$map_comp_header_script = '<script src="https://dapi.kakao.com/v2/maps/sdk.js?appkey='.$this->soo_map_api.'"></script>';
			Context::set('defaultlat', $this->map_comp_lat);
			Context::set('defaultlng', $this->map_comp_lng);
			Context::set('soo_langcode', 'ko');
			Context::set('maps_api_type', $this->maps_api_type);
			Context::set('tpl_path', $tpl_path);
			Context::addHtmlHeader($map_comp_header_script);
		}
		elseif($this->maps_api_type == 'naver')
		{
			$this->map_comp_lat = 37.57;
			$this->map_comp_lng = 126.98;

			if(trim($this->soo_map_api_type) === 'naver_cp')
				$map_comp_header_script = '<script src="https://openapi.map.naver.com/openapi/v3/maps.js?ncpClientId='.$this->soo_map_api.'"></script>';
			elseif(trim($this->soo_map_api_type) === 'naver_gov')
				$map_comp_header_script = '<script src="https://openapi.map.naver.com/openapi/v3/maps.js?govClientId='.$this->soo_map_api.'"></script>';
			else
				$map_comp_header_script = '<script src="https://openapi.map.naver.com/openapi/v3/maps.js?clientId='.$this->soo_map_api.'"></script>';
			Context::set('soo_map_api', $this->soo_map_api);
			Context::set('defaultlat', $this->map_comp_lat);
			Context::set('defaultlng', $this->map_comp_lng);
			Context::set('soo_langcode', 'ko');
			Context::set('maps_api_type', $this->maps_api_type);
			Context::set('tpl_path', $tpl_path);
			Context::addHtmlHeader($map_comp_header_script);
		}
		elseif($this->maps_api_type == 'google')
		{
			if(Context::getLangType() == 'ko') // Seoul
			{
				$this->map_comp_lat = 37.57;
				$this->map_comp_lng = 126.98;
			}
			elseif(Context::getLangType() == 'zh-CN' || Context::getLangType() == 'zh-TW') // Beijing
			{
				$this->map_comp_lat = 39.55;
				$this->map_comp_lng = 116.23;
			}
			else // United States
			{
				$this->map_comp_lat = 38;
				$this->map_comp_lng = -97;
			}

			$map_comp_header_script = '<script src="https://maps.googleapis.com/maps/api/js?key=' . $this->soo_map_api . '&amp;language='.$this->langtype.'"></script>';
			Context::set('defaultlat', $this->map_comp_lat);
			Context::set('defaultlng', $this->map_comp_lng);
			Context::set('soo_langcode',$this->langtype);
			Context::set('tpl_path', $tpl_path);
			Context::set('maps_api_type', $this->maps_api_type);
			Context::addHtmlHeader($map_comp_header_script);
		}
		else
		{
			if(Context::getLangType() == 'ko') // Seoul
			{
				$this->map_comp_lat = 37.57;
				$this->map_comp_lng = 126.98;
			}
			elseif(Context::getLangType() == 'zh-CN' || Context::getLangType() == 'zh-TW') // Beijing
			{
				$this->map_comp_lat = 39.55;
				$this->map_comp_lng = 116.23;
			}
			else // United States
			{
				$this->map_comp_lat = 38;
				$this->map_comp_lng = -97;
			}

			Context::set('defaultlat', $this->map_comp_lat);
			Context::set('defaultlng', $this->map_comp_lng);
			Context::set('soo_langcode',$this->langtype);
			Context::set('tpl_path', $tpl_path);
		}
		$oTemplate = &TemplateHandler::getInstance();
		return $oTemplate->compile($tpl_path, $tpl_file);
	}

	/**
	 * @brief 에디터 컴포넌트가 별도의 고유 코드를 이용한다면 그 코드를 html로 변경하여 주는 method
	 * 이미지나 멀티미디어, 설문등 고유 코드가 필요한 에디터 컴포넌트는 고유코드를 내용에 추가하고 나서
	 * DocumentModule::transContent() 에서 해당 컴포넌트의 transHtml() method를 호출하여 고유코드를 html로 변경
	 * @brief If editor comp. need to translate the code, this func. would translate it to html.
	 * DocumentModule::transContent() would call the transHTML() method.
	 **/
	function transHTML($xml_obj) {
		// API 종류 정하기 다음/네이버/구글
		$this->maps_api_type = $this->getApiHost($this->soo_map_api);

		//한 페이지 내에 지도 수
		$map_count = Context::get('pub_maps_count');

		if(!isset($map_count)) {
			$map_count = 0;
		} else {
			$map_count = $map_count+1;
		}
		Context::set('pub_maps_count' , $map_count);
		$data = unserialize(base64_decode($xml_obj->attrs->alt));
		//지도 표시 시작 start viewing the map.
		$style = trim($xml_obj->attrs->style).';';
		preg_match('/width([ ]*)\:([0-9 a-z\.]+)\;/i', $style, $width_style);
		preg_match('/height([ ]*)\:([0-9 a-z\.]+)\;/i', $style, $height_style);
		if($width_style[2]) $width = intval(trim($width_style[2]));
		if(!$width) $width = intval($xml_obj->attrs->width);
		if(!$width) {$width = 600;}

		if($height_style[2]) $height = intval(trim($height_style[2]));
		if(!$height) $height = intval($xml_obj->attrs->height);
		if(!$height) {$height = 300;}

		$header_script = '';
		if($map_count===0) {
			if($this->maps_api_type == 'daum')
			{
				$header_script .= '<script src="https://dapi.kakao.com/v2/maps/sdk.js?appkey='.$this->soo_map_api.'"></script><script>var ggl_map = [],map_component_user_position = "' . $this->soo_user_position . '";</script><style>div.soo_maps{display:block;position:relative;} div.soo_maps img{max-width:none;}div.soo_maps>a>img{max-width:100%;}</style>'."\n";
			}
			elseif($this->maps_api_type == 'naver')
			{
				
				if(trim($this->soo_map_api_type) === 'naver_cp')
					$header_script .= '<script src="https://openapi.map.naver.com/openapi/v3/maps.js?ncpClientId='.$this->soo_map_api.'"></script>';
				elseif(trim($this->soo_map_api_type) === 'naver_gov')
					$header_script .= '<script src="https://openapi.map.naver.com/openapi/v3/maps.js?govClientId='.$this->soo_map_api.'"></script>';
				else
					$header_script .= '<script src="https://openapi.map.naver.com/openapi/v3/maps.js?clientId='.$this->soo_map_api.'"></script>';

				$header_script .= '<script>var ggl_map = [],map_component_user_position = "' . $this->soo_user_position . '";</script><style>div.soo_maps {display:block;position:relative;} div.soo_maps img {max-width:none;}div.soo_maps>a>img {max-width:100%;}</style>'."\n";
			}
			elseif($this->maps_api_type == 'google')
			{
				$header_script .= '<script src="https://maps.googleapis.com/maps/api/js?key=' . $this->soo_map_api . '&amp;language='.$this->langtype.'"></script><script>var ggl_map = [],map_component_user_position = "' . $this->soo_user_position . '";</script><style>.gmnoprint div[title^="Pan"],.gmnoprint div[title~="이동"] {opacity: 0 !important;}div.soo_maps {display:block;position:relative;} div.soo_maps img {max-width:none;}div.soo_maps>a>img {max-width:100%;}</style>'."\n";
			}
			else
			{
				$header_script .= '<script>var ggl_map = [],map_component_user_position = "' . $this->soo_user_position . '";</script><style>.gmnoprint div[title^="Pan"],.gmnoprint div[title~="이동"] {opacity: 0 !important;}div.soo_maps {display:block;position:relative;} div.soo_maps img {max-width:none;}div.soo_maps>a>img {max-width:100%;}</style>'."\n";
			}
			
		}

		$map_center = explode(',', trim($data->map_center));
		$lat = $map_center[0];
		settype($lat,"float");
		$lng = $map_center[1];;
		settype($lng,"float");

		$map_markers = trim($data->map_markers);
		$map_markers = preg_replace('/[^0-9\.\,\;]+/i', '', $map_markers);

		$zoom = intval(trim($data->map_zoom));
		$this->soo_user_position = ($this->soo_user_position === 'Y') ? 'Y' : 'N';

		if($this->maps_api_type == 'daum')
		{
			$zoom = intval(20-$zoom);
			Context::loadFile(array('./modules/editor/components/map_components/front/js/daum_maps.js', 'head', '', null), true);
			$header_script .= '<script>'.
				'function ggl_map_init'.$map_count.'() {'.
					'var mapOption = { center: new daum.maps.LatLng('.$lat.', '.$lng.'), level: '.$zoom.' };'.
					'var marker_points = "'.$map_markers.'";'.
					'ggl_map['.$map_count.'] = new daum.maps.Map(document.getElementById("ggl_map_canvas'.$map_count.'"), mapOption);'.
					'var zoomControl = new daum.maps.ZoomControl();'.
					'ggl_map['.$map_count.'].addControl(zoomControl, daum.maps.ControlPosition.LEFT);'.
					'var mapTypeControl = new daum.maps.MapTypeControl();'.
					'ggl_map['.$map_count.'].addControl(mapTypeControl, daum.maps.ControlPosition.TOPRIGHT);'.
					'addMarker(ggl_map['.$map_count.'],marker_points)}</script>';
			$zoom = intval(20-$zoom);
		}
		elseif($this->maps_api_type == 'naver')
		{
			$zoom = intval($zoom)-5;
			Context::loadFile(array('./modules/editor/components/map_components/front/js/naver_maps.js', 'head', '', null), true);
			$header_script .= '<script>'.
				'function ggl_map_init'.$map_count.'() {'.
					'var mapOption = { zoom: '.$zoom.', center: new naver.maps.LatLng('.$lat.', '.$lng.'), mapTypeControl: true, zoomControl: true };'.
					'var marker_points = "'.$map_markers.'";'.
					'ggl_map['.$map_count.'] = new naver.maps.Map("ggl_map_canvas'.$map_count.'", mapOption);'.
					'addMarker(ggl_map['.$map_count.'],marker_points)}</script>';
			$zoom = intval($zoom)+5;
		}
		elseif($this->maps_api_type == 'google')
		{
			Context::loadFile(array('./modules/editor/components/map_components/front/js/google_maps.js', 'head', '', null), true);
			$header_script .= '<script>'.
				'function ggl_map_init'.$map_count.'() {'.
					'var mapOption = { zoom: '.$zoom.', mapTypeId: google.maps.MapTypeId.ROADMAP, center: new google.maps.LatLng('.$lat.', '.$lng.'), mapTypeControl: true, mapTypeControlOptions: { style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR, position: google.maps.ControlPosition.TOP_RIGHT }, panControl: false, zoomControl: true, zoomControlOptions: { style: google.maps.ZoomControlStyle.LARGE, position: google.maps.ControlPosition.LEFT_CENTER }, scaleControl: false, streetViewControl: false};'.
					'var marker_points = "'.$map_markers.'";'.
					'ggl_map['.$map_count.'] = new google.maps.Map(document.getElementById("ggl_map_canvas'.$map_count.'"), mapOption);'.
					'addMarker(ggl_map['.$map_count.'],marker_points)}</script>';
		}
		else
		{
			Context::loadFile(array('./modules/editor/components/map_components/front/leaflet/leaflet.js', 'head', '', null), true);
			Context::loadFile(array('./modules/editor/components/map_components/front/leaflet/leaflet.css', '', '', null), true);
			Context::loadFile(array('./modules/editor/components/map_components/front/js/leaflets.js', 'head', '', null), true);
			$header_script .= '<script>'.
				'function ggl_map_init'.$map_count.'() {'.
					'var mapOption = { zoom: ' . $zoom . ', center: new L.latLng('.$lat.', '.$lng.'), layers: L.tileLayer(randomTile(), {attribution: \'Map data &copy; <a target="_blank" href="https://openstreetmap.org">OpenStreetMap</a> contributors, <a target="_blank" href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>\'})};'.
					'var marker_points = "'.$map_markers.'";'.
					'ggl_map['.$map_count.'] = new L.map(document.getElementById("ggl_map_canvas'.$map_count.'"), mapOption);'.
					'ggl_map['.$map_count.'].setZoom(' . $zoom . ');'.
					'L.control.scale().addTo(ggl_map['.$map_count.']);'.
					'addMarker(ggl_map['.$map_count.'],marker_points)}</script>';

		}

		Context::addHtmlHeader($header_script);

		if(Context::getResponseMethod() != 'HTML') {
			$style = 'text-align:center; width: 100%; margin:15px 0px;';
			$view_code .= '<div style="'.$style.'" class="soo_maps"><img src="'.htmlspecialchars($this->getImageMapLink($lat.','.$lng, $map_markers, $zoom, $width, $height)).'" /></div>';

		} elseif(Context::get('act') == 'dispPageAdminContentModify') {
			$view_code = sprintf("<img src='%s' width='%d' height='%d' alt='Map Component' />",str_replace('&amp;amp;','&amp;',htmlspecialchars($xml_obj->attrs->src)), $width, $height);
		} else {
			if($width == 600)
			{
				$width = '100%';
			}
			else
			{
				$width = $width.'px';
			}
			$height = $height.'px';
			$view_code = '<div id="ggl_map_canvas'.$map_count.'" style="position:relative;overflow:hidden;box-sizing:border-box;width:'.$width.';max-width:100%;height:'.$height.'" class="soo_maps"></div>';
			// 이미지 리사이징 애드온 등을 회피하기 위해서 가장 마지막에 실행 되도록 함
			$footer_code = '<script>'.
				'jQuery(window).load(function() { setTimeout(function(){ ggl_map_init'.$map_count.'(); }, 100); });'.
				'</script>';
			Context::addHtmlFooter($footer_code);
		}
		return $view_code;
	}

	function getImageMapLink($center, $markers, $zoom, $width=600, $height=300) {
		$output = "https://maps-api-ssl.google.com/maps/api/staticmap?center=".$center."&zoom=".$zoom."&size=".$width."x".$height;
		$positions = explode(";", $markers);
		foreach($positions as $position) {
			if(!trim($position)) continue;
			$output .= "&markers=size:mid|".$position;
		}
		$output .= "&sensor=false";
		return $output;
	}
}
?>