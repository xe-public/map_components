<?php
// 이전에 구글 지도 컴포넌트를 변형해서 (저장 구조도 모두 바꿀 예정) 국내 지도에 잘 대응하는 컴포넌트 만들기 프로젝트.
/*
생각해볼 것
한국인 경우 네이버 또는 다음 지도 사용.
아닌 경우 구글 지도 사용

한국인지 아닌지는 위도/경도로 구분.... - 필요할까?
*/
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
	function map_components($editor_sequence, $component_path) {
		$this->editor_sequence = $editor_sequence;
		$this->component_path = $component_path;
		Context::loadLang($component_path.'lang');
		if(class_exists('Mobile')) {
			if(Mobile::isFromMobilePhone()) {
				$this->mobile_set = true;
			}
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

		if($this->soo_naver_map_api_key) {
			$uri = sprintf('http://map.naver.com/api/geocode.php?key=%s&encoding=utf-8&coord=latlng&query=%s',$this->soo_naver_map_api_key,urlencode($address));
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
			$uri = sprintf('http://apis.daum.net/local/geo/addr2coord?apikey=%s&q=%s&output=xml',$this->soo_daum_local_api_key,urlencode($address));
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
					$result[$i]->geometry->lng = $input_obj->lng->body;
					$result[$i]->geometry->lat = $input_obj->lat->body;
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
		if(trim($this->soo_map_api))
		{
			if(strlen($this->soo_map_api) == 40)
			{
				$this->maps_api_type = 'daum';
			}
			elseif(strlen($this->soo_map_api) == 32)
			{
				$this->maps_api_type = 'naver';
			}
		}
		if(!$this->maps_api_type)
		{
			$tpl_file = 'popup.html';

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
		else
		{
			$tpl_file = 'kr_map.html';

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
		if(trim($this->soo_map_api))
		{
			if(strlen($this->soo_map_api) == 40)
			{
				$this->maps_api_type = 'daum';
			}
			elseif(strlen($this->soo_map_api) == 32)
			{
				$this->maps_api_type = 'naver';
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

		$header_script = '<style>.gmnoprint div[title^="Pan"],.gmnoprint div[title~="이동"] {opacity: 0 !important;}</style>';
		if($map_count==1) {
			if($this->maps_api_type == 'daum')
			{
				$header_script .= '<script src="https://apis.daum.net/maps/maps3.js?apikey='.$this->soo_map_api.'"></script><style type="text/css">span.soo_maps {display:block;} span.soo_maps img {max-width:none;}span.soo_maps>a>img {max-width:100%;}</style>'."\n";
			}
			else
			{
				$header_script .= '<script src="https://maps-api-ssl.google.com/maps/api/js?sensor=false&amp;language='.$this->langtype.'"></script><style type="text/css">span.soo_maps {display:block;} span.soo_maps img {max-width:none;}span.soo_maps>a>img {max-width:100%;}</style>'."\n";
			}
			
		}
		if(!$data->location_no) { // 단일 위치 지도 one pointed map
			$map_center = explode(',', trim($data->map_center));
			$lat = $map_center[0];
			settype($lat,"float");
			$lng = $map_center[1];;
			settype($lng,"float");

			$map_markers = explode(',', trim($data->map_markers));
			
			$marker_lat = $map_markers[0];
			settype($marker_lat,"float");
			$marker_lng = $map_markers[1];
			settype($marker_lng,"float");
			$zoom = trim($data->map_zoom);
			settype($zoom,"int");

			$map_locations = array();
			$map_locations[0] = array(
						'map_lat' => $lat,
						'map_lng' => $lng,
						'marker_lng' => $marker_lng,
						'marker_lat' => $marker_lat,
						'map_zoom' => $zoom
						);

			if($this->maps_api_type == 'daum')
			{
				$zoom = intval(20-$zoom);
				$header_script .= '<script>'.
					'function ggl_map_init'.$map_count.'() {'.
						'var mapOption = { level: '.$zoom.', center: new daum.maps.LatLng('.$lat.', '.$lng.') };'.
						'var ggl_map'.$map_count.' = new daum.maps.Map(document.getElementById("ggl_map_canvas'.$map_count.'"), mapOption);'.
						'var zoomControl = new daum.maps.ZoomControl();'.
						'ggl_map'.$map_count.'.addControl(zoomControl, daum.maps.ControlPosition.LEFT);'.
						'var mapTypeControl = new daum.maps.MapTypeControl();'.
						'ggl_map'.$map_count.'.addControl(mapTypeControl, daum.maps.ControlPosition.TOPRIGHT);'.
						'var ggl_marker'.$map_count.' = new daum.maps.Marker({ position: new daum.maps.LatLng('.$marker_lat.', '.$marker_lng.') });'.
						'ggl_marker'.$map_count.'.setMap(ggl_map'.$map_count.');'.
						'ggl_marker'.$map_count.'.setDraggable(false);'.'}</script>';
			}
			else
			{
				$header_script .= '<script>'.
					'function ggl_map_init'.$map_count.'() {'.
						'var mapOption = { zoom: '.$zoom.', mapTypeId: google.maps.MapTypeId.ROADMAP };'.
						'var ggl_map'.$map_count.' = new google.maps.Map(document.getElementById("ggl_map_canvas'.$map_count.'"), mapOption);'.
						'ggl_map'.$map_count.'.setCenter(new google.maps.LatLng('.$lat.', '.$lng.'));'.
						'var ggl_markerlatlng'.$map_count.' = new google.maps.LatLng('.$marker_lat.', '.$marker_lng.');'.
						'var ggl_marker'.$map_count.' = new google.maps.Marker({ position: ggl_markerlatlng'.$map_count.', map: ggl_map'.$map_count.', draggable: false});'.
						'ggl_marker'.$map_count.'.setMap(ggl_map'.$map_count.');'.'}</script>';
			}

			Context::addHtmlHeader($header_script);
		} else { // 다중 위치 지도 map of numerous point
			settype($data->location_no,"int");
			$map_locations = array();

			$header_script .= '<script>'.
				'function ggl_map_init'.$map_count.'() {'.
					'var mapOption = { zoom:8,mapTypeControl: '.$maptypeCtrl.', mapTypeId: google.maps.MapTypeId.ROADMAP };'.
					'var infowindow = new google.maps.InfoWindow({content: ""}); var ggl_map'.$map_count.' = new google.maps.Map(document.getElementById("ggl_map_canvas'.$map_count.'"), mapOption);'."\n";
				for($i=0;$i<$data->location_no;$i++) {
					$lat = trim($data->{'map_lat'.$i});
					settype($lat,"float");
					$lng = trim($data->{'map_lng'.$i});
					settype($lng,"float");
					$marker_lng = trim($data->{'marker_lng'.$i});
					settype($marker_lng,"float");
					$marker_lat = trim($data->{'marker_lat'.$i});
					settype($marker_lat,"float");
					$zoom = trim($data->{'map_zoom'.$i});
					settype($zoom,"int");
					if(!$lat || !$lng || !$marker_lng || !$marker_lat || !$zoom) {
						return 'f';
						break;
					}


					//getMobileMaps() 이용할것.
					$map_locations[] = array(
						'map_lat' => $lat,
						'map_lng' => $lng,
						'marker_lng' => $marker_lng,
						'marker_lat' => $marker_lat,
						'map_zoom' => $zoom
					);

					if($i==0) {
						$header_script .= 'ggl_map'.$map_count.'.setCenter(new google.maps.LatLng('.$lat.', '.$lng.'));'.'ggl_map'.$map_count.'.setZoom('.$zoom.');'."\n";
					}
					$header_script .= 'var ggl_markerlatlng'.$map_count.'_'.$i.' = new google.maps.LatLng('.$marker_lat.', '.$marker_lng.');'.
						'var ggl_marker'.$map_count.'_'.$i.' = new google.maps.Marker({ position: ggl_markerlatlng'.$map_count.'_'.$i.', map: ggl_map'.$map_count.', draggable: false});'.
						'ggl_marker'.$map_count.'_'.$i.'.setMap(ggl_map'.$map_count.');'."\n";

					$header_script .= 'google.maps.event.addListener(ggl_marker'.$map_count.'_'.$i.', \'click\', function(){'.
						'ggl_map'.$map_count.'.setZoom('.$zoom.');'.
						'ggl_map'.$map_count.'.panTo(new google.maps.LatLng('.$lat.', '.$lng.'));'.
						'infowindow.close();'."\n";
					$header_script .=  '});'.'ggl_marker'.$map_count.'_'.$i.'.setMap(ggl_map'.$map_count.');'."\n";
				}

				$header_script .= '}</script>';
			Context::addHtmlHeader($header_script);
		}

		if(Context::getResponseMethod() != 'HTML') {
			if(count($map_locations) > 0)
			{
				$view_code = '';
				foreach($map_locations as $key => $location)
				{
					$style = 'text-align:center; width: 100%; margin:15px 0px;';
					$view_code .= '<div style="'.$style.'" class="soo_maps"><img src="'.htmlspecialchars($this->getImageMapLink(($location['map_lat'].','.$location['map_lng']), ($location['marker_lat'].','.$location['marker_lng']), $location['map_zoom'], $width, $height)).'" /></div>';
				}
			}
		} elseif(Context::get('act') == 'dispPageAdminContentModify') {
			$view_code = sprintf("<img src='%s' width='%d' height='%d' alt='Map Component' />",str_replace('&amp;amp;','&amp;',htmlspecialchars($xml_obj->attrs->src)), $width, $height);
		} else {
			$view_code = '<span id="ggl_map_canvas'.$map_count.'" style="width: '.$width.'px; height: '.$height.'px" class="soo_maps"></span>'.
				'<script>'.
				'jQuery(window).load(function() { ggl_map_init'.$map_count.'(); });'.
				'</script>'."\n";
		}
		return $view_code;
	}

	function altViewGMap() {
		$this->mobile_set = false;
		if(class_exists('Mobile')) {
			if(Mobile::isMobileCheckByAgent()) {
				$this->mobile_set = true;
			}
		}

		if($this->mobile_set == true) {
			return $this->viewImageMap();
		} else {
			return $this->viewScriptMap();
		}
	}
	
	function viewScriptMap() {
		// 모바일 및 RSS용 페이지 필요.
		$header_script = '';

		$header_script .= '<script type="text/javascript" src="https://maps-api-ssl.google.com/maps/api/js?sensor=true"></script>'."\n";
		$location_no = intval(Context::get('location_no'));

		if(trim($this->soo_maptypecontrol)) $maptypeCtrl = 'false';
		else $maptypeCtrl = 'true';

		if($location_no>1) {
			$header_script .= '<script type="text/javascript">//<![CDATA['.
				'<!--'.
				'function ggl_map_init() {'.
					'var mapOption = { zoom:8, mapTypeControl: '.$maptypeCtrl.' mapTypeId: google.maps.MapTypeId.ROADMAP }'.
					'var infowindow = new google.maps.InfoWindow({content: ""}); var ggl_map = new google.maps.Map(document.getElementById("ggl_map_canvas"), mapOption);'."\n";
			for($i=0;$i<$location_no;$i++) {
				$lat = trim(Context::get('map_lat'.$i));
				settype($lat,"float");
				$lng = trim(Context::get('map_lng'.$i));
				settype($lng,"float");
				$marker_lng = trim(Context::get('marker_lng'.$i));
				settype($marker_lng,"float");
				$marker_lat = trim(Context::get('marker_lat'.$i));
				settype($marker_lat,"float");
				$zoom = trim(Context::get('map_zoom'.$i));
				settype($zoom,"int");
				if(!$lat || !$lng || !$marker_lng || !$marker_lat || !$zoom) {
					return 'f';
					break;
				}
				if($i==0) {
					$header_script .= 'ggl_map.setCenter(new google.maps.LatLng('.$lat.', '.$lng.'));'.'ggl_map.setZoom('.$zoom.');'."\n";
				}
				$header_script .= 'var ggl_markerlatlng_'.$i.' = new google.maps.LatLng('.$marker_lat.', '.$marker_lng.');'.
					'var ggl_marker_'.$i.' = new google.maps.Marker({ position: ggl_markerlatlng_'.$i.', map: ggl_map, draggable: false});'.
					'ggl_marker_'.$i.'.setMap(ggl_map);'."\n";

				$header_script .= 'google.maps.event.addListener(ggl_marker_'.$i.', \'click\', function(){'.
					'ggl_map.setZoom('.$zoom.');'.
					'ggl_map.panTo(new google.maps.LatLng('.$lat.', '.$lng.'));'.
					'infowindow.close();'."\n";
				$header_script .=  '});'.'ggl_marker_'.$i.'.setMap(ggl_map);'."\n";
			}
			$header_script .= '}'.'//-->'.'//]]>'.'</script>';

		} else {
			$lat = trim(Context::get('map_lat'));
			settype($lat,"float");
			$lng = trim(Context::get('map_lng'));
			settype($lng,"float");
			$marker_lng = trim(Context::get('marker_lng'));
			settype($marker_lng,"float");
			$marker_lat = trim(Context::get('marker_lat'));
			settype($marker_lat,"float");
			$zoom = trim(Context::get('map_zoom'));
			settype($zoom,"int");

			$header_script .= '<script type="text/javascript">//<![CDATA['.
				'//<!--'.
				'function ggl_map_init() {'.
					'var mapOption = { zoom: '.$zoom.', mapTypeControl: '.$maptypeCtrl.' mapTypeId: google.maps.MapTypeId.ROADMAP }'.
					'var ggl_map = new google.maps.Map(document.getElementById("ggl_map_canvas"), mapOption);'.
					'ggl_map.setCenter(new google.maps.LatLng('.$lat.', '.$lng.'));'.
					'var ggl_markerlatlng = new google.maps.LatLng('.$marker_lat.', '.$marker_lng.');'.
					'var ggl_marker = new google.maps.Marker({ position: ggl_markerlatlng, map: ggl_map, draggable: false});'.
					'var infowindow = new google.maps.InfoWindow({ content: \'\' });'.
					'ggl_marker.setMap(ggl_map);'."\n";
				$header_script .= '}'.'//-->'.'//]]>'.'</script>';
		}
		$view_code = '<div id="ggl_map_canvas"></div>'."\n";

		header("Content-Type: text/html; charset=UTF-8");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header("Set-Cookie: ");
		print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"'.
			'"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'.
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="ko" xml:lang="ko">'.
			'<head>'.
			'<meta http-equiv="Content-type" content="text/html;charset=UTF-8" />'.
			'<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>'.
			'<style type="text/css">'.
			'html { height: 100%; }'.
			'body { height: 100%; margin: 0px; padding: 0px }'.
			'#ggl_map_canvas { height: 100% }'.
			'</style>'.
			$header_script.
			'<title></title>'.
			'</head>'.
			'<body onload="ggl_map_init();">'.
			$view_code.
			'</body>'.
			'</html>';
		exit();

	}

	function getImageMapLink($center, $marker, $zoom, $width=320, $height=400) {
		return sprintf("https://maps-api-ssl.google.com/maps/api/staticmap?language=%s&center=%s&zoom=%s&size=%sx%s&markers=size:mid|%s&sensor=false", $this->langtype, $center, $zoom, intval($width), intval($height), $marker);
	}

}
?>