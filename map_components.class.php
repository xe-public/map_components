<?php
// 이전에 구글 지도 컴포넌트를 변형해서 (저장 구조도 모두 바꿀 예정) 국내 지도에 잘 대응하는 컴포넌트 만들기 프로젝트.
class map_components extends EditorHandler {
	var $editor_sequence = '0';
	var $component_path = '';
	var $mobile_set = false;

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
		$tpl_file = 'popup.html';
		$this->soo_google_lat=trim($this->soo_google_lat);
		$this->soo_google_lng=trim($this->soo_google_lng);
		$this->soo_google_remove_normal_map=trim($this->soo_google_remove_normal_map);
		if($this->soo_google_lat=='') {
			if(Context::getLangType() == 'ko') $this->soo_google_lat = 37.56308554496544;
			else $this->soo_google_lat = 51.5001524;
		}
		if($this->soo_google_lng=='') {
			if(Context::getLangType() == 'ko') $this->soo_google_lng = 126.98796272277832;
			else $this->soo_google_lng = -0.1262362;
		}
		if($this->soo_naver_map_api_key || $this->soo_yahoo_map_api_key) $naverapi_check = 1;
		else $naverapi_check = 0;

		//language setting
		$xe_langtype = array(
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
		$google_langtype = array(
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
		$google_langtype = str_replace($xe_langtype, $google_langtype, strtolower(Context::getLangType()));

		$soo_google_map_api_header_script = '<script type="text/javascript" src="https://maps-api-ssl.google.com/maps/api/js?sensor=true&amp;language='.$google_langtype.'"></script>'; 
		$soo_google_map_api_header_script .= '<script type="text/javascript">//<![CDATA['."\n";
		$soo_google_map_api_header_script .= sprintf(
			'var insert_lat="%s";'."\n".
			'var insert_lng="%s";'."\n".
			'var defaultlat="%s";'."\n".
			'var defaultlng="%s";'."\n".
			'var naver_api_check="%s";'."\n".
			'function soo_map_set() {'."\n".
			''."\n"
			,$this->soo_insert_lat,$this->soo_insert_lng,$this->soo_google_lat,$this->soo_google_lng, $naverapi_check);
		$soo_google_map_api_header_script .= '}'."\n".'//]]>';
		$soo_google_map_api_header_script .= '</script>'."\n";
		Context::set('soo_langcode',$google_langtype);
		Context::set('tpl_path', $tpl_path);
		Context::addHtmlHeader($soo_google_map_api_header_script);
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
		// RSS, 모바일용 대체링크 문자열 (지울것)
		//getMobileMaps() 이용할것.
		$altMapLinkParas = array();

		//한 페이지 내에 지도 수
		$map_count = Context::get('google_map_count');
		if(!$map_count) {
			$map_count=1;
		} else {
			$map_count=$map_count+1;
		}
		Context::set('google_map_count' , $map_count);

		//지도 표시 시작 start viewing the map.
		$width = $xml_obj->attrs->width;
		settype($width,"int");
		if(!$width) {$width = 600;}
		$height = $xml_obj->attrs->height;
		settype($height,"int");
		if(!$height) {$height = 400;}

		if(trim($this->soo_maptypecontrol)) $maptypeCtrl = 'false';
		else $maptypeCtrl = 'true';

		$header_script = '';
		if($map_count==1) {

			//language setting
			$xe_langtype = array(
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
			$google_langtype = array(
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
			$google_langtype = str_replace($xe_langtype, $google_langtype, strtolower(Context::getLangType()));

			$header_script .= '<script type="text/javascript" src="https://maps-api-ssl.google.com/maps/api/js?sensor=true&amp;language='.$google_langtype.'"></script><style type="text/css">.soo_google_map_ment { background-color: #ffffff; color: #000000; } span.soo_maps {display:block;} span.soo_maps img {max-width:none;}span.soo_maps>a>img {max-width:100%;}</style>'."\n";
		}
		if(!$xml_obj->attrs->location_no) { // 단일 위치 지도 one pointed map
			$ment = str_replace(array('[[STS[[',']]STS]]','[[STS_EQ]]'),array('<','>','='),$xml_obj->attrs->ment);
			$ori_ment = $ment;
			$ori_ment = strip_tags($ori_ment);
			$ori_ment = htmlspecialchars($ori_ment);
			if($ment) {
				$ment = htmlspecialchars($ment);
				$ment = preg_replace('/&lt;br([^&]*)&gt;/i','<br />',$ment);
				$ment = preg_replace('/&lt;hr([^&]*)&gt;/i','<hr />',$ment);
				$ment = preg_replace('/&lt;([a-z]*)&gt;/i','<\\1>',$ment);
				$ment = preg_replace('/&lt;\/([a-z]*)&gt;/i','</\\1>',$ment);
				$ment = eregi_replace('<script>','&lt;script&gt;',$ment);
				$ment = eregi_replace('</script>','&lt;/script&gt;',$ment);
				$ment = eregi_replace('<style>','&lt;style&gt;',$ment);
				$ment = eregi_replace('</style>','&lt;/style&gt;',$ment);
			}
			if($ment) $ment = sprintf("<div class=\\\"soo_google_map_ment\\\">%s</div>",$ment);
			
			$lat = trim($xml_obj->attrs->map_lat);
			settype($lat,"float");
			$lng = trim($xml_obj->attrs->map_lng);
			settype($lng,"float");
			$marker_lng = trim($xml_obj->attrs->marker_lng);
			settype($marker_lng,"float");
			$marker_lat = trim($xml_obj->attrs->marker_lat);
			settype($marker_lat,"float");
			$zoom = trim($xml_obj->attrs->map_zoom);
			settype($zoom,"int");

			//$altMapLinkParas .= sprintf('&amp;location_no=1&amp;ment=%s&amp;map_lat=%s&amp;map_lng=%s&amp;marker_lng=%s&amp;marker_lat=%s&amp;map_zoom=%s',urlencode(),$lat,$lng,$marker_lng,$marker_lat,$zoom);

			//getMobileMaps() 이용할것.
			$map_locations = array();
			$map_locations[0] = array('ment' => $xml_obj->attrs->ment,
						'map_lat' => $lat,
						'map_lng' => $lng,
						'marker_lng' => $marker_lng,
						'marker_lat' => $marker_lat,
						'map_zoom' => $zoom,
						'ment' => $ori_ment
						);
			$altMapLinkParas = $this->getMobileMaps($map_locations);

			$header_script .= '<script type="text/javascript">//<![CDATA['."\n".
				'<!--'."\n".
				'function ggl_map_init'.$map_count.'() {'."\n".
					'var mapOption = { zoom: '.$zoom.', mapTypeControl: '.$maptypeCtrl.', mapTypeId: google.maps.MapTypeId.ROADMAP };'."\n".
					'var ggl_map'.$map_count.' = new google.maps.Map(document.getElementById("ggl_map_canvas'.$map_count.'"), mapOption);'."\n".
					'ggl_map'.$map_count.'.setCenter(new google.maps.LatLng('.$lat.', '.$lng.'));'."\n".
					'var ggl_markerlatlng'.$map_count.' = new google.maps.LatLng('.$marker_lat.', '.$marker_lng.');'."\n".
					'var ggl_marker'.$map_count.' = new google.maps.Marker({ position: ggl_markerlatlng'.$map_count.', map: ggl_map'.$map_count.', draggable: false});'."\n".
					'ggl_marker'.$map_count.'.setMap(ggl_map'.$map_count.');'."\n";
				if($ment) {
					$header_script .= 'infowindow = new google.maps.InfoWindow({content: "'.$ment.'", disableAutoPan: true}).open(ggl_map'.$map_count.',ggl_marker'.$map_count.')'."\n";
				}
				$header_script .= '}'."\n".'//-->'."\n".'//]]>'."\n".'</script>';
			Context::addHtmlHeader($header_script);
		} else { // 다중 위치 지도 map of numerous point
			settype($xml_obj->attrs->location_no,"int");
			$map_locations = array();

			$header_script .= '<script type="text/javascript">//<![CDATA['."\n".
				'<!--'."\n".
				'function ggl_map_init'.$map_count.'() {'."\n".
					'var mapOption = { zoom:8,mapTypeControl: '.$maptypeCtrl.', mapTypeId: google.maps.MapTypeId.ROADMAP };'."\n".
					'var infowindow = new google.maps.InfoWindow({content: ""}); var ggl_map'.$map_count.' = new google.maps.Map(document.getElementById("ggl_map_canvas'.$map_count.'"), mapOption);'."\n";
				for($i=0;$i<$xml_obj->attrs->location_no;$i++) {
					$ment = str_replace(array('[[STS[[',']]STS]]','[[STS_EQ]]'),array('<','>','='),$xml_obj->attrs->{'ment'.$i});
					$ori_ment = $ment;
					$ori_ment = strip_tags($ori_ment);
					$ori_ment = htmlspecialchars($ori_ment);
					if($ment) {
						$ment = htmlspecialchars($ment);
						$ment = preg_replace('/&lt;br([^&]*)&gt;/i','<br />',$ment);
						$ment = preg_replace('/&lt;hr([^&]*)&gt;/i','<hr />',$ment);
						$ment = preg_replace('/&lt;([a-z]*)&gt;/i','<\\1>',$ment);
						$ment = preg_replace('/&lt;\/([a-z]*)&gt;/i','</\\1>',$ment);
						$ment = eregi_replace('<script>','&lt;script&gt;',$ment);
						$ment = eregi_replace('</script>','&lt;/script&gt;',$ment);
						$ment = eregi_replace('<style>','&lt;style&gt;',$ment);
						$ment = eregi_replace('</style>','&lt;/style&gt;',$ment);
					}
					if($ment) $ment = sprintf("<div class=\\\"soo_google_map_ment\\\">%s</div>",$ment);
					$lat = trim($xml_obj->attrs->{'map_lat'.$i});
					settype($lat,"float");
					$lng = trim($xml_obj->attrs->{'map_lng'.$i});
					settype($lng,"float");
					$marker_lng = trim($xml_obj->attrs->{'marker_lng'.$i});
					settype($marker_lng,"float");
					$marker_lat = trim($xml_obj->attrs->{'marker_lat'.$i});
					settype($marker_lat,"float");
					$zoom = trim($xml_obj->attrs->{'map_zoom'.$i});
					settype($zoom,"int");
					if(!$lat || !$lng || !$marker_lng || !$marker_lat || !$zoom) {
						return 'f';
						break;
					}


					//getMobileMaps() 이용할것.
					$map_locations[] = array('ment' => $xml_obj->attrs->ment,
						'map_lat' => $lat,
						'map_lng' => $lng,
						'marker_lng' => $marker_lng,
						'marker_lat' => $marker_lat,
						'map_zoom' => $zoom,
						'ment' => $ori_ment
					);

					if($i==0) {
						$header_script .= 'ggl_map'.$map_count.'.setCenter(new google.maps.LatLng('.$lat.', '.$lng.'));'."\n".'ggl_map'.$map_count.'.setZoom('.$zoom.');'."\n";
					}
					$header_script .= 'var ggl_markerlatlng'.$map_count.'_'.$i.' = new google.maps.LatLng('.$marker_lat.', '.$marker_lng.');'."\n".
						'var ggl_marker'.$map_count.'_'.$i.' = new google.maps.Marker({ position: ggl_markerlatlng'.$map_count.'_'.$i.', map: ggl_map'.$map_count.', draggable: false});'."\n".
						'ggl_marker'.$map_count.'_'.$i.'.setMap(ggl_map'.$map_count.');'."\n";

					$header_script .= 'google.maps.event.addListener(ggl_marker'.$map_count.'_'.$i.', \'click\', function(){'."\n".
						'ggl_map'.$map_count.'.setZoom('.$zoom.');'."\n".
						'ggl_map'.$map_count.'.panTo(new google.maps.LatLng('.$lat.', '.$lng.'));'."\n".
						'infowindow.close();'."\n";
					if($ment) {
						$header_script .= 'infowindow = new google.maps.InfoWindow({content: "'.$ment.'"});'."\n".
						'infowindow.open(ggl_map'.$map_count.',ggl_marker'.$map_count.'_'.$i.');'."\n";
					}
					$header_script .=  '});'."\n".'ggl_marker'.$map_count.'_'.$i.'.setMap(ggl_map'.$map_count.');'."\n";
				}

				$altMapLinkParas = $this->getMobileMaps($map_locations);

				$header_script .= '}'."\n".'//-->'."\n".'//]]>'."\n".'</script>';
			Context::addHtmlHeader($header_script);
		}

		if(Context::getResponseMethod() != 'HTML' || $this->mobile_set == true) {
			if(count($altMapLinkParas) > 0)
			{
				$view_code = '';
				foreach($altMapLinkParas as $key => $point)
				{
					$location = $map_locations[$key];
					$style = 'text-align:center; width: 100%; margin:15px 0px;';
					$view_code .= '<span style="'.$style.'" class="soo_maps"><a href="'.$altMapLinkParas[0].'" target="_blank"><img src="'.
						htmlspecialchars($this->getImageMapLink(($location['map_lat'].','.$location['map_lng']), ($location['marker_lat'].','.$location['marker_lng']), $location['map_zoom'], $width, $height)).'" alt="'.$location['ment'].'" />'.$location['ment'].'<br />'.Context::getLang('view_map').'</a></span><br />';
				}
			}
		} else {
			$view_code = '<span id="ggl_map_canvas'.$map_count.'" style="width: '.$width.'px; height: '.$height.'px" class="soo_maps"></span>'."\n".
				'<script language="javascript" type="text/javascript">//<![CDATA['."\n".
				'<!--'."\n".
				'jQuery(window).load(function() { ggl_map_init'.$map_count.'(); });'."\n".
				'//-->'."\n".'//]]></script>'."\n";
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
			$header_script .= '<script type="text/javascript">//<![CDATA['."\n".
				'<!--'."\n".
				'function ggl_map_init() {'."\n".
					'var mapOption = { zoom:8, mapTypeControl: '.$maptypeCtrl.' mapTypeId: google.maps.MapTypeId.ROADMAP }'."\n".
					'var infowindow = new google.maps.InfoWindow({content: ""}); var ggl_map = new google.maps.Map(document.getElementById("ggl_map_canvas"), mapOption);'."\n";
			for($i=0;$i<$location_no;$i++) {
				$ment = str_replace(array('[[STS[[',']]STS]]','[[STS_EQ]]'),array('<','>','='),Context::get('ment'.$i));
				if($ment) {
					$ment = htmlspecialchars($ment);
					$ment = preg_replace('/&lt;br([^&]*)&gt;/i','<br />',$ment);
					$ment = preg_replace('/&lt;hr([^&]*)&gt;/i','<hr />',$ment);
					$ment = preg_replace('/&lt;([a-z]*)&gt;/i','<\\1>',$ment);
					$ment = preg_replace('/&lt;\/([a-z]*)&gt;/i','</\\1>',$ment);
					$ment = eregi_replace('<script>','&lt;script&gt;',$ment);
					$ment = eregi_replace('</script>','&lt;/script&gt;',$ment);
					$ment = eregi_replace('<style>','&lt;style&gt;',$ment);
					$ment = eregi_replace('</style>','&lt;/style&gt;',$ment);
				}
				if($ment) $ment = sprintf("<div class=\\\"soo_google_map_ment\\\">%s</div>",$ment);
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
					$header_script .= 'ggl_map.setCenter(new google.maps.LatLng('.$lat.', '.$lng.'));'."\n".'ggl_map.setZoom('.$zoom.');'."\n";
				}
				$header_script .= 'var ggl_markerlatlng_'.$i.' = new google.maps.LatLng('.$marker_lat.', '.$marker_lng.');'."\n".
					'var ggl_marker_'.$i.' = new google.maps.Marker({ position: ggl_markerlatlng_'.$i.', map: ggl_map, draggable: false});'."\n".
					'ggl_marker_'.$i.'.setMap(ggl_map);'."\n";

				$header_script .= 'google.maps.event.addListener(ggl_marker_'.$i.', \'click\', function(){'."\n".
					'ggl_map.setZoom('.$zoom.');'."\n".
					'ggl_map.panTo(new google.maps.LatLng('.$lat.', '.$lng.'));'."\n".
					'infowindow.close();'."\n";
				if($ment) {
					$header_script .= 'infowindow = new google.maps.InfoWindow({content: "'.$ment.'"});'."\n".
					'infowindow.open(ggl_map,ggl_marker_'.$i.');'."\n";
				}
				$header_script .=  '});'."\n".'ggl_marker_'.$i.'.setMap(ggl_map);'."\n";
			}
			$header_script .= '}'."\n".'//-->'."\n".'//]]>'."\n".'</script>';

		} else {
			$ment = str_replace(array('[[STS[[',']]STS]]','[[STS_EQ]]'),array('<','>','='),Context::get('ment'));
			if($ment) {
				$ment = htmlspecialchars($ment);
				$ment = preg_replace('/&lt;br([^&]*)&gt;/i','<br />',$ment);
				$ment = preg_replace('/&lt;hr([^&]*)&gt;/i','<hr />',$ment);
				$ment = preg_replace('/&lt;([a-z]*)&gt;/i','<\\1>',$ment);
				$ment = preg_replace('/&lt;\/([a-z]*)&gt;/i','</\\1>',$ment);
				$ment = eregi_replace('<script>','&lt;script&gt;',$ment);
				$ment = eregi_replace('</script>','&lt;/script&gt;',$ment);
				$ment = eregi_replace('<style>','&lt;style&gt;',$ment);
				$ment = eregi_replace('</style>','&lt;/style&gt;',$ment);
			}
			if($ment) $ment = sprintf("<div class=\\\"soo_google_map_ment\\\">%s</div>",$ment);
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

			$header_script .= '<script type="text/javascript">//<![CDATA['."\n".
				'//<!--'."\n".
				'function ggl_map_init() {'."\n".
					'var mapOption = { zoom: '.$zoom.', mapTypeControl: '.$maptypeCtrl.' mapTypeId: google.maps.MapTypeId.ROADMAP }'."\n".
					'var ggl_map = new google.maps.Map(document.getElementById("ggl_map_canvas"), mapOption);'."\n".
					'ggl_map.setCenter(new google.maps.LatLng('.$lat.', '.$lng.'));'."\n".
					'var ggl_markerlatlng = new google.maps.LatLng('.$marker_lat.', '.$marker_lng.');'."\n".
					'var ggl_marker = new google.maps.Marker({ position: ggl_markerlatlng, map: ggl_map, draggable: false});'."\n".
					'var infowindow = new google.maps.InfoWindow({ content: \'\' });'."\n".
					'ggl_marker.setMap(ggl_map);'."\n";
				if($ment) {
					$header_script .= 'infowindow = new google.maps.InfoWindow({content: "'.$ment.'", disableAutoPan: true}).open(ggl_map,ggl_marker);'."\n";
				}
				$header_script .= '}'."\n".'//-->'."\n".'//]]>'."\n".'</script>';
		}
		$view_code = '<div id="ggl_map_canvas"></div>'."\n";

		header("Content-Type: text/html; charset=UTF-8");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		header("Set-Cookie: ");
		print '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"'."\n".
			'"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">'."\n".
			'<html xmlns="http://www.w3.org/1999/xhtml" lang="ko" xml:lang="ko">'."\n".
			'<head>'."\n".
			'<meta http-equiv="Content-type" content="text/html;charset=UTF-8" />'."\n".
			'<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>'."\n".
			'<style type="text/css">'."\n".
			'html { height: 100%; }'."\n".
			'body { height: 100%; margin: 0px; padding: 0px }'."\n".
			'#ggl_map_canvas { height: 100% }'."\n".
			'</style>'."\n".
			$header_script."\n".
			'<title></title>'."\n".
			'</head>'."\n".
			'<body onload="ggl_map_init();">'."\n".
			$view_code."\n".
			'</body>'."\n".
			'</html>';
		exit();

	}

	function getMobileMaps($locations = array()) {
		// 모바일용 링크 가져오기
		$location_no = count($locations);
		$return = array();
		if($location_no < 1) return NULL;

		foreach($locations as $key => $point) {
			$ment = str_replace(array('[[STS[[',']]STS]]','[[STS_EQ]]'),array('<','>','='),$point['ment']);
			if($ment) {
				$ment = htmlspecialchars($ment);
			}

			$lat = trim($point['map_lat']);
			settype($lat,"float");
			$lng = trim($point['map_lng']);
			settype($lng,"float");
			$marker_lng = trim($point['marker_lng']);
			settype($marker_lng,"float");
			$marker_lat = trim($point['marker_lat']);
			settype($marker_lat,"float");
			$zoom = trim($point['map_zoom']);
			settype($zoom,"int");
			if(!$lat || !$lng || !$marker_lng || !$marker_lat || !$zoom) {
				break;
			}

			$return[$key] = htmlspecialchars($this->getGoogleMapLink(($lat.','.$lng), ($marker_lat.','.$marker_lng), $zoom, $ment));
		}

		return $return;
	}

	function getImageMapLink($center, $marker, $zoom, $width=320, $height=400) {
		//language setting
		$xe_langtype = array(
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
		$google_langtype = array(
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
		$google_langtype = str_replace($xe_langtype, $google_langtype, strtolower(Context::getLangType()));
		return sprintf("https://maps-api-ssl.google.com/maps/api/staticmap?language=%s&center=%s&zoom=%s&size=%sx%s&markers=size:mid|%s&sensor=false", $google_langtype, $center, $zoom, intval($width), intval($height), $marker);
	}

	function getGoogleMapLink($center, $marker, $zoom, $ment = '') {
		return sprintf("https://maps.google.com?ll=%s&z=%s&q=%s@%s&iwloc=A&iwd=1", $center, $zoom, urlencode($ment), $marker);
	}

}
?>