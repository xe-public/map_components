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
		if(!$height) {$height = 400;}

		$this->add("width", $width);
		$this->add("height", $height);
		$this->add("results", $data);
	}

	function xml_api_request($uri, $headers = null) {
		$xml = '';
		$xml = FileHandler::getRemoteResource($uri, null, 3, 'GET', 'application/xml', $headers);

		$xml = preg_replace("/<\?xml([.^>]*)\?>/i", "", $xml);

		$oXmlParser = new XmlParser();
		$xml_doc = $oXmlParser->parse($xml);

		return $xml_doc;
	}

	function search() {
		$address = Context::get('address');
		if(!$address) return;

		// API 종류 정하기 다음/네이버/구글
		if(trim($this->soo_map_api))
		{
			if($this->soo_map_api === $this->soo_daum_local_api_key || strlen($this->soo_map_api) === 40)
			{
				if(!$this->soo_daum_local_api_key && strlen($this->soo_map_api) === 40)
				{
					$this->soo_daum_local_api_key = $this->soo_map_api;
				}
				$this->maps_api_type = 'daum';
			}
			elseif(strlen($this->soo_map_api) == 32)
			{
				$this->maps_api_type = 'naver';
			}
			else
			{
				$this->maps_api_type = 'google';
			}
		}

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
			$uri = sprintf('http://map.naver.com/api/geocode.php?key=%s&encoding=utf-8&coord=latlng&query=%s',$this->soo_map_api,urlencode($address));
			$xml_doc = $this->xml_api_request($uri);

			$item = $xml_doc->geocode->item;
			if(!is_array($item)) $item = array($item);
			$item_count = count($item);

			if($item_count > 0) {
				$result_orgin_count = count($result);
				for($i=$result_orgin_count;($i-$result_orgin_count)<$item_count;$i++) {
					$input_obj = '';
					$j = $i-$result_orgin_count;
					$input_obj = $item[$j];
					if(!$input_obj->address->body) continue;
					$result[$i]->formatted_address = $input_obj->address->body;
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
		if(trim($this->soo_map_api))
		{
			if($this->soo_map_api === $this->soo_daum_local_api_key || strlen($this->soo_map_api) === 40)
			{
				$this->maps_api_type = 'daum';
			}
			elseif(strlen($this->soo_map_api) == 32)
			{
				$this->maps_api_type = 'naver';
			}
			else
			{
				$this->maps_api_type = 'google';
			}
		}

		// 다음과 네이버는 국내 지도만 사용가능. 구글은 세계지도.
		if($this->maps_api_type == 'daum')
		{
			$this->map_comp_lat = 37.57;
			$this->map_comp_lng = 126.98;

			$map_comp_header_script = '<script src="https://apis.daum.net/maps/maps3.js?apikey='.$this->soo_map_api.'"></script>';
			$map_comp_header_script .= '<script>'.
				sprintf(
					'var defaultlat="%s";'.
					'var defaultlng="%s";'
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

			$map_comp_header_script = '<script src="http://openapi.map.naver.com/openapi/naverMap.naver?ver=2.0&amp;key='.$this->soo_map_api.'"></script>';
			$map_comp_header_script .= '<script>'.
				sprintf(
					'var defaultlat="%s";'.
					'var defaultlng="%s";'
					,$this->map_comp_lat,$this->map_comp_lng).
				'</script>';
			Context::set('soo_langcode', 'ko');
			Context::set('maps_api_type', $this->maps_api_type);
			Context::set('tpl_path', $tpl_path);
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

			$map_comp_header_script = '<script src="https://maps-api-ssl.google.com/maps/api/js?sensor=false&amp;language='.$this->langtype.'"></script>';
			$map_comp_header_script .= '<script>'.
				sprintf(
					'var defaultlat="%s";'.
					'var defaultlng="%s";'
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
		if(trim($this->soo_map_api))
		{
			if($this->soo_map_api === $this->soo_daum_local_api_key || strlen($this->soo_map_api) === 40)
			{
				$this->maps_api_type = 'daum';
			}
			elseif(strlen($this->soo_map_api) == 32)
			{
				$this->maps_api_type = 'naver';
			}
			else
			{
				$this->maps_api_type = 'google';
			}
		}

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
		if(!$height) {$height = 400;}

		$header_script = '';
		if($map_count==1) {
			if($this->maps_api_type == 'daum')
			{
				$header_script .= '<script src="https://apis.daum.net/maps/maps3.js?apikey='.$this->soo_map_api.'"></script><style type="text/css">span.soo_maps {display:block;} span.soo_maps img {max-width:none;}span.soo_maps>a>img {max-width:100%;}</style>'."\n";
			}
			elseif($this->maps_api_type == 'naver')
			{
				$header_script .= '<script src="http://openapi.map.naver.com/openapi/naverMap.naver?ver=2.0&amp;key='.$this->soo_map_api.'"></script><style type="text/css">span.soo_maps {display:block;} span.soo_maps img {max-width:none;}span.soo_maps>a>img {max-width:100%;}</style>'."\n";
			}
			else
			{
				$header_script .= '<script src="https://maps-api-ssl.google.com/maps/api/js?sensor=false&amp;language='.$this->langtype.'"></script><style type="text/css">.gmnoprint div[title^="Pan"],.gmnoprint div[title~="이동"] {opacity: 0 !important;}span.soo_maps {display:block;} span.soo_maps img {max-width:none;}span.soo_maps>a>img {max-width:100%;}</style>'."\n";
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

		if($this->maps_api_type == 'daum')
		{
			$zoom = intval(20-$zoom);
			Context::loadFile(array('./modules/editor/components/map_components/front/js/daum_maps.js', 'head', '', null), true);
			$header_script .= '<script>'.
				'function ggl_map_init'.$map_count.'() {'.
					'var mapOption = { level: '.$zoom.', center: new daum.maps.LatLng('.$lat.', '.$lng.') };'.
					'var marker_points = "'.$map_markers.'";'.
					'var ggl_map'.$map_count.' = new daum.maps.Map(document.getElementById("ggl_map_canvas'.$map_count.'"), mapOption);'.
					'var zoomControl = new daum.maps.ZoomControl();'.
					'ggl_map'.$map_count.'.addControl(zoomControl, daum.maps.ControlPosition.LEFT);'.
					'var mapTypeControl = new daum.maps.MapTypeControl();'.
					'ggl_map'.$map_count.'.addControl(mapTypeControl, daum.maps.ControlPosition.TOPRIGHT);'.
					'addMarker(ggl_map'.$map_count.',marker_points)}</script>';
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
					'var ggl_map'.$map_count.' = new nhn.api.map.Map("ggl_map_canvas'.$map_count.'", mapOption);'.
					'var zoomControl = new nhn.api.map.ZoomControl();'.
					'ggl_map'.$map_count.'.addControl(zoomControl);'.
					'zoomControl.setPosition({ top : 10, left : 10 });'.
					'var mapTypeControl = new nhn.api.map.MapTypeBtn();'.
					'ggl_map'.$map_count.'.addControl(mapTypeControl);'.
					'mapTypeControl.setPosition({ top : 10, right : 10 });'.
					'addMarker(ggl_map'.$map_count.',marker_points)}</script>';
			$zoom = intval($zoom)+5;
		}
		else
		{
			Context::loadFile(array('./modules/editor/components/map_components/front/js/google_maps.js', 'head', '', null), true);
			$header_script .= '<script>'.
				'function ggl_map_init'.$map_count.'() {'.
					'var mapOption = { zoom: '.$zoom.', mapTypeId: google.maps.MapTypeId.ROADMAP, center: new google.maps.LatLng('.$lat.', '.$lng.'), mapTypeControl: true, mapTypeControlOptions: { style: google.maps.MapTypeControlStyle.HORIZONTAL_BAR, position: google.maps.ControlPosition.TOP_RIGHT }, panControl: false, zoomControl: true, zoomControlOptions: { style: google.maps.ZoomControlStyle.LARGE, position: google.maps.ControlPosition.LEFT_CENTER }, scaleControl: false, streetViewControl: false};'.
					'var marker_points = "'.$map_markers.'";'.
					'var ggl_map'.$map_count.' = new google.maps.Map(document.getElementById("ggl_map_canvas'.$map_count.'"), mapOption);'.
					'addMarker(ggl_map'.$map_count.',marker_points)}</script>';
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
			$view_code = '<span id="ggl_map_canvas'.$map_count.'" style="box-sizing:border-box;width:'.$width.'; height:'.$height.'" class="soo_maps"></span>'.
				'<script>'.
				'jQuery(window).load(function() { ggl_map_init'.$map_count.'(); });'.
				'</script>'."\n";
		}
		return $view_code;
	}

	function getImageMapLink($center, $markers, $zoom, $width=600, $height=400) {
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