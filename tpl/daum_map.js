/* Map Component by MinSoo Kim. (c) 2014 MinSoo Kim. (misol.kr@gmail.com) */
var map_zoom = 5, map_lat = '', map_lng = '', map = '', marker = '', map_markers = [], map_marker_positions = '', modi_marker_pos = '', saved_location = [], result_array = [], result_from = '';
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
function toggle(id)
{
	obj=document.getElementById(id);

	if(obj.style.display == "none") obj.style.display="block";
	else obj.style.display="none";
}
function map_point(i) { //검색된 위치 정보를 배열에서 로드
	center = result_array[i].geometry.location;
	map.setCenter(center);
}
function view_list() { //검색된 위치 정보를 배열에서 리스트로 뿌림
	var html = '';
	if(result_array.length === 0) 
	{
		alert(no_result);
		return;
	}
	for(var i=0;i<result_array.length;i++) {
		if(i === 0) {
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

function showLocation(address) {
	result_from = '';
	if(!address) return;

	var params = [];
	params.component = "map_components";
	params.address = address;
	params.method = "search";

	var response_tags = ['error','message','results'];
	exec_xml('editor', 'procEditorCall', params, function(a,b) { complete_search(a,b,address); }, response_tags);
}

function complete_search(ret_obj, response_tags, address) {
	var results = ret_obj.results;
	if(results) results = results.item;
	else results = [];

	address_adder(results);
}
function address_adder(results) {
	result_array = [];
	if(typeof(results.length) == "undefined") results = [results];

	for(var i=0;i<results.length;i++) {
		if(results[i].formatted_address || results[i].formatted_address !== null) {
			result_array[i] = { from: results[i].result_from,
				formatted_address: results[i].formatted_address,
				geometry: {location : new daum.maps.LatLng(results[i].geometry.lat, results[i].geometry.lng) } };
		}
	}
	view_list();
}

function getMaps() {
	var node;
	var mapOption = {
		level: map_zoom,
		center: new daum.maps.LatLng(defaultlat, defaultlng)
	};
	map = new daum.maps.Map(document.getElementById("map_canvas"), mapOption);

	if(typeof(opener) !="undefined" && opener !== null)
	{
		node = opener.editorPrevNode;
	}

	if(typeof(node) !="undefined" && node && node.nodeName == "IMG") {
		var img_var = {
				'component': 'map_components',
				'method': 'decode_data',
				'style': node.getAttribute('style'),
				'data': node.getAttribute('alt')
			};
		var img_data = [];

		var response_tags = ['error','message','width','height','results'];
		exec_xml('editor', 'procEditorCall', img_var, function(ret_obj, b) {
				jQuery("#width").val(parseInt(ret_obj.width,10));
				jQuery("#height").val(parseInt(ret_obj.height,10));

				img_data = ret_obj.results;

				var center_split = img_data.map_center.split(',');
				center = new daum.maps.LatLng(center_split[0], center_split[1]);
				map.setCenter(center);

				var markers_split = img_data.map_markers.split(';');
				map_marker_positions = img_data.map_markers.trim();
				marker = addMarker(0);

				map_zoom = 20 - parseInt(img_data.map_zoom,10);
				if(!map_zoom) map_zoom = 5;
				map.setLevel(map_zoom);
			}, response_tags);
	} else {
		center = new daum.maps.LatLng(defaultlat, defaultlng);
		map.setCenter(center);
		var center = map.getCenter();

		jQuery("#width").val('600');
		jQuery("#height").val('300');
		map.setLevel(map_zoom);
	}

	var zoomControl = new daum.maps.ZoomControl();
	map.addControl(zoomControl, daum.maps.ControlPosition.LEFT);
	var mapTypeControl = new daum.maps.MapTypeControl();
	map.addControl(mapTypeControl, daum.maps.ControlPosition.TOPRIGHT);

	daum.maps.event.addListener(map, 'click', function(MouseEvent) {
		latlng = MouseEvent.latLng;
		addMarker(latlng);
	});

}

/* 새로운 위치에 마커 추가. latlng = 0 인 경우, map_marker_positions 에 지정된 마커 새로 찍음 */
function addMarker(latlng) {
	/* jshint -W083 */
	var i;
	var new_marker_obj;
	/* 전체 구조는 removeMarker() 와 동일*/
	// 마커 일단 다 제거
	if(typeof(map_markers) != "undefined") {
		for(i = 0; i < map_markers.length; i++)
		{
			map_markers[i].setMap(null);
		}
	}

	if(latlng !== 0) {
		var latitude = latlng.getLat();
		var longitude = latlng.getLng();

		// 중복되는 마커는 생성되지 않도록.
		map_marker_positions = map_marker_positions.replace(latitude+','+longitude+';', '');
		map_marker_positions += latitude + ',' + longitude + ';'; /* removeMarker() 와 다른 곳 */
	}

	positions = makeLocationArray(map_marker_positions);

	// 전체 마커 다시 생성
	for(i = 0; i < positions.length; i++)
	{
		map_markers[i] = new daum.maps.Marker({
			position: positions[i]
		});
		map_markers[i].setMap(map);
		map_markers[i].setDraggable(true);
		map_markers[i].soo_position = positions[i];
		new_marker_obj = map_markers[i];

		// 이벤트 등록 드래그 시작과 끝은 전후 관계로 연결 되어있음
		daum.maps.event.addListener(map_markers[i], "dragstart", function() {
			var position = this.soo_position;
			map_marker_positions = map_marker_positions.replace(position.getLat() + ',' + position.getLng() + ';', '');
		});
		daum.maps.event.addListener(map_markers[i], "dragend", function() {
			var position = this.getPosition();
			// 중복되는 마커는 생성되지 않도록.
			map_marker_positions = map_marker_positions.replace(position.getLat() + ',' + position.getLng() + ';', '');
			map_marker_positions += position.getLat() + ',' + position.getLng() + ';';
			addMarker(0);
		});
		daum.maps.event.addListener(map_markers[i], "click", function() {
			var position = this.soo_position;
			removeMarker(position);
		});
	}

	// 추가된 마커가 배열의 가장 마지막에 있을거란 가정 하에 마지막 마커 리턴
	return new_marker_obj;

}
function removeMarker(latlng) {
	/* jshint -W083 */
	var i;
	/* 전체 구조는 removeMarker() 와 동일*/
	// 마커 일단 다 제거
	for(i = 0; i < map_markers.length; i++)
	{
		map_markers[i].setMap(null);
	}

	var latitude = latlng.getLat();
	var longitude = latlng.getLng();

	// 마커 위치 제거
	map_marker_positions = map_marker_positions.replace(latitude+','+longitude+';', '');
	positions = makeLocationArray(map_marker_positions);

	// 전체 마커 다시 생성
	for(i = 0; i < positions.length; i++)
	{
		map_markers[i] = new daum.maps.Marker({
			position: positions[i]
		});
		map_markers[i].setMap(map);
		map_markers[i].setDraggable(true);
		map_markers[i].soo_position = positions[i];
		new_marker_obj = map_markers[i];

		// 이벤트 등록 드래그 시작과 끝은 전후 관계로 연결 되어있음
		daum.maps.event.addListener(map_markers[i], "dragstart", function() {
			var position = this.soo_position;
			map_marker_positions = map_marker_positions.replace(position.getLat() + ',' + position.getLng() + ';', '');
		});
		daum.maps.event.addListener(map_markers[i], "dragend", function() {
			var position = this.getPosition();
			map_marker_positions = map_marker_positions.replace(position.getLat() + ',' + position.getLng() + ';', '');
			map_marker_positions += position.getLat() + ',' + position.getLng() + ';';
			addMarker(0);
		});
		daum.maps.event.addListener(map_markers[i], "click", function() {
			var position = this.soo_position;
			removeMarker(position);
		});
	}

}

function makeLocationArray(str_position) {
	var arr_positons = [];
	var positions = str_position.split(";");
	for(var i = 0; i < positions.length; i++)
	{
		if(!positions[i].trim()) continue;
		var position = positions[i].split(",");
		arr_positons[i] = new daum.maps.LatLng(position[0],position[1]);
	}
	return arr_positons;
}
function makeLocationStr(arr_position) {
	var str_positons = '';

	for(var i = 0; i < arr_position.length; i++)
	{
		str_positons += arr_position[i].getLat() + ',' + arr_position[i].getLng() + ';';
	}
	return str_positons;
}
function insertMap(obj) {
	if(typeof(opener)=="undefined" || !opener) return;
	var width = jQuery("#width").val(), height = jQuery("#height").val();

	map_zoom = 20 - map.getLevel();
	map_lat = map.getCenter().getLat();
	map_lng = map.getCenter().getLng();
	if(!width) {width = '600';}
	if(!height) {height = '300';}

	//XE에서 속성 삭제하는 방향으로 바뀐다면, alt 에 넣자
	var img_var = {
		'component': 'map_components',
		'method': 'encode_data',
		'map_center': map_lat+','+map_lng,
		'width': width,
		'height': height,
		'map_markers': map_marker_positions,
		'map_zoom': map_zoom
	};
	var img_data = '';

	var response_tags = ['error','message','results'];
	exec_xml('editor', 'procEditorCall', img_var, function(ret_obj,b) {
		var results = ret_obj.results; img_data = results;

		positions = makeLocationArray(map_marker_positions);
		static_markers = [];

		// 전체 마커 위치
		for(i = 0; i < positions.length; i++)
		{
			static_markers[i] ={
				position: positions[i]
			};
		}

		var dummy_map = document.createElement("div"), 
			dummy_option = {
				center: new daum.maps.LatLng(map_lat, map_lng),
				level: 20 - map_zoom,
				marker: static_markers
			};

		var static_map = new daum.maps.StaticMap(dummy_map, dummy_option);
		static_map = jQuery(dummy_map).find('img');
		static_map_src = jQuery(static_map).attr('src');
		static_map_src = static_map_src.replace('IW=0', 'IW=' + width);
		static_map_src = static_map_src.replace('IH=0', 'IH=' + height);

		var text = "<img src=\""+static_map_src+"\" editor_component=\"map_components\" alt=\""+img_data+"\" style=\"border:2px dotted #FF0033; no-repeat center;width: "+width+"px; height: "+height+"px;\" />";

		opener.editorFocus(opener.editorPrevSrl);
		var iframe_obj = opener.editorGetIFrame(opener.editorPrevSrl);
		opener.editorReplaceHTML(iframe_obj, text);
		opener.editorFocus(opener.editorPrevSrl);
		window.close();
	}, response_tags);

}
jQuery(document).ready(function() { getMaps(); });
