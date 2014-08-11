/* Map Component by MinSoo Kim. (c) 2014 MinSoo Kim. (misol.kr@gmail.com) */
var map_zoom = 13, map_lat = '', map_lng = '', marker_latlng = '', map = '', marker = '', map_markers = new Array(), map_marker_positions = '', saved_location = new Array(), result_array = new Array(), infowindow = '', result_from = '';
/*

** 2014 08 11 TODO LIST **

map_marker_positions 는 lat,lng;lat,lng; 형식으로 마커들의 위치를 모두 포함하는 컨테이너.
- 지도 처음 로딩시 마커 하나도 없음
- 마커가 추가될 때 위치 추가
- 마커가 제거될 때 위치 제거
- 마커 이동은 제거 후 추가로 간주.
- 검색 결과에서 결과 항목을 클릭하는 것은 지도 위치만 이동.


마커를 움직이면, 처음 마커 위치를 map_marker_positions 에서 찾아서, 움직임이 끝난 곳의 위치로 치환.
마커를 더블클릭하면 map_marker_positions 에서 찾아서 마커를 삭제하고, 맵에서도 마커 삭제
지도를 더블클릭하면 더블클릭한 위치에 마커 생성하고 map_marker_positions 에서 마커 추가.
*/
function map_point(i) { //검색된 위치 정보를 배열에서 로드
	center = result_array[i].geometry.location;

	map.setCenter(center);
	latlng = center;
	marker_latlng = result_array[i].geometry.location;
	marker_code = marker_latlng.lat() + marker_latlng.lng();
	marker.setMap(null);
	marker = new google.maps.Marker({
		position: marker_latlng, 
		map: map,
		draggable: true
	});
	soo_marker_event();
	marker.setMap(map);
	infowindow.close();

	infowindow = new google.maps.InfoWindow({
		content: dragmarkertext + "<br /><strong>" + result_array[i].formatted_address + "</strong>",
		disableAutoPan: true
	});
	infowindow.open(map,marker);
}
function view_list() { //검색된 위치 정보를 배열에서 리스트로 뿌림
	var html = '';
	if(result_array.length == 0) 
	{
		alert(no_result);
		return;
	}
	for(var i=0;i<result_array.length;i++) {
		if(i==0) {
			html += '<ul id="view_list">';
		}
		if(result_array.length==1) { map_point('0'); }
		var format_split = result_array[i].formatted_address.split(" ");
		var list_address = result_array[i].formatted_address.substring(result_array[i].formatted_address.lastIndexOf(format_split[format_split.length-3]));  
		html += "<li class=\"result_lists\"><a href=\"javascript:map_point('"+i+"');\">"+ list_address +"</a></li>";
	}
	html += '</ul>';
	jQuery("#result_list_layer").html(html);
	window.location.href = '#view_list';
}
function addAddressToMap(response, status) {
	if(status==200) { result_from = 'naver'; }
	else if(status == google.maps.GeocoderStatus.OK) { result_from = 'google'; }

	if (status != google.maps.GeocoderStatus.OK && status != 200) {
		alert(no_result + "\nGoogle Error Code : "+status);
	} else {
		result_array = new Array();
		result_array = response;
		view_list();
	}
}
function showLocation(address) {
	result_from = '';
	if(!address) return;

	var params = new Array();
	params['component'] = "map_components";
	params['address'] = address;
	params['method'] = "search";

	var response_tags = new Array('error','message','results');
	exec_xml('editor', 'procEditorCall', params, function(a,b) { complete_search(a,b,address); }, response_tags);
}
function complete_search(ret_obj, response_tags, address) {
	var results = ret_obj['results'];
	if(results) results = results.item;
	else results = new Array();

	address_adder(results);
	//geocoder.geocode({'address': address}, function(a,b) {address_adder(a, b,address,results); });
}
function address_adder(results) {
	var response = new Array();
	if(typeof(results.length) == "undefined") results = new Array(results);

	for(var i=0;i<results.length;i++) {
		if(results[i].formatted_address || results[i].formatted_address != null) {
			response[i] = { from: results[i].result_from,
				formatted_address: results[i].formatted_address,
				geometry: {location : new google.maps.LatLng(results[i].geometry.lat, results[i].geometry.lng) } };
		}
	}
	addAddressToMap(response, 200);
}
function soo_marker_event() {
	google.maps.event.addListener(marker, "dragstart", function() {
		infowindow.close();
	});
	google.maps.event.addListener(marker, "dragend", function(event) {
		if(event.latLng) {
			geocoder.geocode({'latLng': event.latLng}, function(rst, stat) {
				if (stat == google.maps.GeocoderStatus.OK) {
					if (rst[1]) {
						infowindow.close();
						infowindow = new google.maps.InfoWindow({ content: dragmarkertext+"<br />"+rst[1].formatted_address });
						infowindow.open(map,marker);
					}
					else {
						infowindow.close();
						infowindow = new google.maps.InfoWindow({ content: soo_about_marker });
						infowindow.open(map,marker);
					}
				}
			});
			marker_latlng = event.latLng;

		}
	});
}
function getMaps() {
	var mapOption = {
		zoom: 8,
		mapTypeId: google.maps.MapTypeId.ROADMAP
	}
	map = new google.maps.Map(document.getElementById("map_canvas"), mapOption);

	infowindow = new google.maps.InfoWindow();

	if(typeof(opener) !="undefined" && opener != null)
	{
		var node = opener.editorPrevNode;
	}

	if(typeof(node) !="undefined" && node && node.nodeName == "IMG") {
		var img_var = {
				'component': 'map_components',
				'method': 'decode_data',
				'data': node.getAttribute('alt')
			};
		var img_data = new Array();

		var response_tags = new Array('error','message','results');
		exec_xml('editor', 'procEditorCall', img_var, function(ret_obj,b) {
				img_data = ret_obj['results'];

				saved_location['zoom'] = img_data['map_zoom'];

				saved_location['center'] = new Array();
				var center_split = img_data['map_center'].split(',');
				saved_location['center']['lat'] = center_split[0];
				saved_location['center']['lng'] = center_split[1];

				if(!img_data['location_no']) {
					var marker_split = img_data['map_markers'].split(',');
					saved_location[0] = new Array();
					saved_location[0]['lat'] = marker_split[0];
					saved_location[0]['lng'] = marker_split[1];

					map_lat = saved_location['center']['lat'];
					map_lng = saved_location['center']['lng'];
					marker_lat = saved_location[0]['lat'];
					marker_lng = saved_location[0]['lng'];
					marker_latlng = new google.maps.LatLng(marker_lat, marker_lng);
					latlng = marker_latlng;
					map_zoom = parseInt(img_data['map_zoom'],10);
					if(marker_latlng) {
						latlng = marker_latlng
					}
					if(map_zoom) {
						jQuery("#map_zoom").val(map_zoom);
					}
					if(map_lat) {
						jQuery("#lat").val(map_lat);
					}
					if(map_lng) {
						jQuery("#lng").val(map_lng);
					}
				} else {
					var location_no = parseInt(img_data['location_no'],10);

					var markers_split = img_data['map_markers'].split(';');
					for(i=0;i<location_no;i++) {
						if(!markers_split[i]) continue;
						var marker_split = markers_split[i].split(',');
						saved_location[i] = new Array();
						saved_location[i]['lat'] = marker_split[0];
						saved_location[i]['lng'] = marker_split[1];
					}

					map_lat = saved_location['center']['lat'];
					map_lng = saved_location['center']['lng'];
					marker_lat = saved_location[0]['lat'];
					marker_lng = saved_location[0]['lng'];
					marker_latlng = new google.maps.LatLng(marker_lat, marker_lng);
					latlng = marker_latlng;
					map_zoom = parseInt(saved_location['zoom'],10);
					if(marker_latlng) {
						latlng = marker_latlng;
					}
					if(map_zoom) {
						jQuery("#map_zoom").val(map_zoom);
					}
					if(map_lat) {
						jQuery("#lat").val(map_lat);
					}
					if(map_lng) {
						jQuery("#lng").val(map_lng);
					}
				}
				map.setCenter(new google.maps.LatLng(map_lat, map_lng));
				map.setZoom(map_zoom);
				var center = map.getCenter();

				map.setZoom(map_zoom);

				marker = new google.maps.Marker({
						position: latlng,
						map: map, 
						draggable: true
					});
				soo_marker_event();
				marker.setMap(map);
				geocoder = new google.maps.Geocoder();
				jQuery("#lng").val(center.lng());
				jQuery("#lat").val(center.lat());
				jQuery("#map_zoom").value = map.getZoom();
				marker_latlng = latlng;
				infowindow.close();
			}, response_tags);
		/* ============================================ */
	} else {
		jQuery("#lat").val(defaultlat);
		map_lat = defaultlat;
		jQuery("#lng").val(defaultlng);
		map_lng = defaultlng;
		map.setCenter(new google.maps.LatLng(map_lat, map_lng));
		var center = map.getCenter();
		marker_latlng = center;
		jQuery("#width").val('600');
		jQuery("#height").val('400');
		latlng = center;
		map.setZoom(map_zoom);

		marker = new google.maps.Marker({
				position: latlng,
				map: map, 
				draggable: true
			});
		soo_marker_event();
		marker.setMap(map);
		geocoder = new google.maps.Geocoder();
		jQuery("#lng").val(center.lng());
		jQuery("#lat").val(center.lat());
		jQuery("#map_zoom").value = map.getZoom();
		marker_latlng = latlng;
		infowindow.close();

	}

	google.maps.event.addListener(map, 'dragend', function() {
		center = map.getCenter();
		jQuery("#lng").val(center.lng());
		jQuery("#lat").val(center.lat());
		jQuery("#map_zoom").val(map.getZoom());
		var bounds = map.getBounds();
		var southWest = bounds.getSouthWest();
		var northEast = bounds.getNorthEast();
		if((latlng.lng()<southWest.lng() || northEast.lng()<latlng.lng()) || (latlng.lat()<southWest.lat() || northEast.lat()<latlng.lat())) {
			marker.setMap(null);
			infowindow.close();
			latlng = center;
			marker_latlng = latlng;
			marker = new google.maps.Marker({
				position: center, 
				map: map,
				draggable: true
			});
			marker.setMap(map);
			infowindow = new google.maps.InfoWindow({
				content: dragmarkertext,
				disableAutoPan: true
			});
			infowindow.open(map,marker);
			soo_marker_event();
		}
	});
	google.maps.event.addListener(map, 'dblclick', function(event) {
		center = event.latLng;
		jQuery("#lng").val(center.lng());
		jQuery("#lat").val(center.lat());
		jQuery("#map_zoom").val(map.getZoom());
		var bounds = map.getBounds();
		var southWest = bounds.getSouthWest();
		var northEast = bounds.getNorthEast();
		if((latlng.lng()<southWest.lng() || northEast.lng()<latlng.lng()) || (latlng.lat()<southWest.lat() || northEast.lat()<latlng.lat())) {
			marker.setMap(null);
			infowindow.close();
			latlng = center;
			marker_latlng = latlng;
			marker = new google.maps.Marker({
				position: event.latLng, 
				map: map,
				draggable: true
			});
			marker.setMap(map);
			infowindow = new google.maps.InfoWindow({
				content: dragmarkertext,
				disableAutoPan: true
			});
			infowindow.open(map,marker);
			soo_marker_event();
		}
	});

}
function insertMap(obj) {
	if(typeof(opener)=="undefined" || !opener) return;
	var width = jQuery("#width").val(), height = jQuery("#height").val();
	if(saved_location.length == 0 || saved_location.length == 1) {
		map_zoom = map.getZoom();
		map_lat = map.getCenter().lat();;
		map_lng = map.getCenter().lng();;
		if(!width) {width = '600'}
		if(!height) {height = '400'}
		if(!map_zoom) {map_zoom = '13'}
//XE에서 속성 삭제하는 방향으로 바뀐다면, longd 에 넣자
		var img_var = {
				'component': 'map_components',
				'method': 'encode_data',
				'map_center': map_lat+','+map_lng,
				'width': width,
				'height': height,
				'map_markers': marker_latlng.lat()+","+marker_latlng.lng(),
				'map_zoom': map_zoom
			};
		var img_data = '';

		var response_tags = new Array('error','message','results');
		exec_xml('editor', 'procEditorCall', img_var, function(ret_obj,b) { 
				var results = ret_obj['results']; img_data = results;
				var text = "<img src=\"https://maps-api-ssl.google.com/maps/api/staticmap?center="+map_lat+','+map_lng+"&zoom="+map_zoom+"&size="+width+"x"+height+"&markers=size:mid|"+marker_latlng.lat()+','+marker_latlng.lng()+"&sensor=false\" editor_component=\"map_components\" alt=\""+img_data+"\" style=\"border:2px dotted #FF0033; no-repeat center;width: "+width+"px; height: "+height+"px;\" />";
				opener.editorFocus(opener.editorPrevSrl);
				var iframe_obj = opener.editorGetIFrame(opener.editorPrevSrl)
				opener.editorReplaceHTML(iframe_obj, text);
				opener.editorFocus(opener.editorPrevSrl);
				window.close();

			}, response_tags);
// dsfad
		
	} else {
// 안씀
		var text = "<img src=\"https://maps-api-ssl.google.com/maps/api/staticmap?center="+saved_location[0][1]+','+saved_location[0][2]+"&zoom="+saved_location[0][0]+"&size="+width+"x"+height+"&sensor=false\" editor_component=\"map_components\" width=\""+width+"\" height=\""+height+"\" style=\"width:"+width+"px;height:"+height+"px;border:2px dotted #FF0033;\"";
		text += ' location_no="' + saved_location.length + '"';
		text += ' map_zoom="' + saved_location['zoom'] + '"';
		text += ' map_center="' + saved_location['center']['lat'] + ',' + saved_location['center']['lng'];

		text += ' map_markers="';
		for(var i=0;i<saved_location.length;i++) {
			text += saved_location[i][3] + ',' + saved_location[i][4] + ';';
		}
		text += '" />';
	}
}
jQuery(document).ready(function() { getMaps(); });
