<?php
class map_compontnts extends EditorHandler {
	var $editor_sequence = '0';
	var $component_path = '';

	//language setting
	var $langtype = 'ko';
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

	function search() {
		$address = Context::get('address');
		if(!$address) return;

		// 구글 주소-좌표 변환
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

		// 네이버 주소-좌표 변환
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

		// 다음 주소-좌표 변환
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
}
?>