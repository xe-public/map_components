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
			elseif(strlen($api_key) == 32 || strlen($api_key) == 20)
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
		$xml = FileHandler::getRemoteResource($uri, null, 3, 'GET', 'application/xml', $headers, array(), array(), $request_config);

		$xml = preg_replace("/<\?xml([.^>]*)\?>/i", "", $xml);

		$oXmlParser = new XmlParser();
		$xml_doc = $oXmlParser->parse($xml);

		return $xml_doc;
	}

	function search() {
		$address = Context::get('address');
		if(!$address) return;

		// API 종류 정하기 다음/네이버/구글
		$this->maps_api_type = $this->getApiHost($this->soo_map_api);

		$uri = sprintf('http://maps.googleapis.com/maps/api/geocode/xml?address=%s&sensor=false&language=%s',urlencode($address),urlencode($this->langtype));
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

		if($this->maps_api_type == 'naver') {
			$uri = sprintf('https://openapi.naver.com/v1/map/geocode?key=%s&encoding=utf-8&output=xml&coord=latlng&query=%s',$this->soo_map_api,urlencode($address));
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

		if($this->soo_daum_local_api_key) {
			$uri = sprintf('http://apis.daum.net/local/v1/search/keyword.xml?apikey=%s&query=%s&output=xml',$this->soo_daum_local_api_key,urlencode($address));
			$xml_doc = $this->xml_api_request($uri);

			$item = $xml_doc->channel->item;
			if(!is_array($item)) $item = array($item);
			$item_count = count($item);

			if($item_count > 0) {
				$result_orgin_count = count($result);
				for($i=$result_orgin_count;($i-$result_orgin_count)<$item_count;$i++) {
					$input_obj = '';
					$j = $i-$result_orgin_count;
					$input_obj = $item[$j];
					if(!$input_obj->title->body) continue;
					$result[$i]->formatted_address = $input_obj->title->body;
					$result[$i]->geometry->lng = $input_obj->longitude->body;
					$result[$i]->geometry->lat = $input_obj->latitude->body;
					$result[$i]->result_from = 'Daum';
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

			$map_comp_header_script = '<script src="https://apis.daum.net/maps/maps3.js?apikey='.$this->soo_map_api.'"></script>';
			$map_comp_header_script .= '<script>'.
				sprintf(
					'var defaultlat = "%s";'.
					'var defaultlng = "%s";'
					,$this->map_comp_lat,$this->map_comp_lng).
				'</script>';
			Context::set('soo_langcode', 'ko');
			Context::set('maps_api_type', $this->maps_api_type);
			Context::set('tpl_path', $tpl_path);
			Context::addHtmlHeader($map_comp_header_script);
		}
		elseif($this->maps_api_type == 'naver')
		{
			$this->map_comp_lat = 37.57;
			$this->map_comp_lng = 126.98;

			$map_comp_header_script = '<script src="https://openapi.map.naver.com/openapi/v2/maps.js?clientId='.$this->soo_map_api.'"></script>';
			$map_comp_header_script .= '<script>'.
				sprintf(
					'var defaultlat = "%s";'.
					'var defaultlng = "%s";'
					,$this->map_comp_lat,$this->map_comp_lng).
				'</script>';
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
			$map_comp_header_script .= '<script>'.
				sprintf(
					'var defaultlat="%s";'.
					'var defaultlng="%s";'
					,$this->map_comp_lat,$this->map_comp_lng).
				'</script>';
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

			$map_comp_header_script = '';
			$map_comp_header_script .= '<script>'.
				sprintf(
					'var defaultlat = %s;'.
					'var defaultlng = %s;'
					,$this->map_comp_lat,$this->map_comp_lng).
				'</script>';
			Context::set('soo_langcode',$this->langtype);
			Context::set('tpl_path', $tpl_path);
			Context::addHtmlHeader($map_comp_header_script);
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
		if(!$map_count) {
			$map_count=1;
		} else {
			$map_count=$map_count+1;
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
		if($map_count==1) {
			if($this->maps_api_type == 'daum')
			{
				$header_script .= '<script src="https://apis.daum.net/maps/maps3.js?apikey='.$this->soo_map_api.'"></script><script>var ggl_map = [],map_component_user_position = "' . $this->soo_user_position . '";</script><style>div.soo_maps{display:block;position:relative;} div.soo_maps img{max-width:none;}div.soo_maps>a>img{max-width:100%;}</style>'."\n";
			}
			elseif($this->maps_api_type == 'naver')
			{
				$header_script .= '<script src="https://openapi.map.naver.com/openapi/v2/maps.js?clientId='.$this->soo_map_api.'"></script><script>var ggl_map = [],map_component_user_position = "' . $this->soo_user_position . '";</script><style>div.soo_maps {display:block;position:relative;} div.soo_maps img {max-width:none;}div.soo_maps>a>img {max-width:100%;}</style>'."\n";
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
					'var mapOption = { zoom: '.$zoom.', point: new nhn.api.map.LatLng('.$lat.', '.$lng.'), enableWheelZoom : true, enableDragPan : true, enableDblClickZoom : true, mapMode : 0, activateTrafficMap : false, activateBicycleMap : false, };'.
					'var marker_points = "'.$map_markers.'";'.
					'ggl_map['.$map_count.'] = new nhn.api.map.Map("ggl_map_canvas'.$map_count.'", mapOption);'.
					'var zoomControl = new nhn.api.map.ZoomControl();'.
					'ggl_map['.$map_count.'].addControl(zoomControl);'.
					'zoomControl.setPosition({ top : 10, left : 10 });'.
					'var mapTypeControl = new nhn.api.map.MapTypeBtn();'.
					'ggl_map['.$map_count.'].addControl(mapTypeControl);'.
					'mapTypeControl.setPosition({ top : 10, right : 10 });'.
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
					'var mapOption = { zoom: ' . $zoom . ', center: new L.latLng('.$lat.', '.$lng.'), layers: L.tileLayer(randomTile(), {attribution: \'Map data &copy; <a target="_blank" href="http://openstreetmap.org">OpenStreetMap</a> contributors, <a target="_blank" href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>\'})};'.
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