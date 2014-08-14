/* Map Component by MinSoo Kim. (c) 2014 MinSoo Kim. (misol.kr@gmail.com) */
var map_zoom = 5, map_lat = '', map_lng = '', map = '', marker = '', map_markers = new Array(), map_marker_positions = '', modi_marker_pos = '', saved_location = new Array(), result_array = new Array(), result_from = '';
/*

** 2014 08 11 TODO LIST **

map_marker_positions �� lat,lng;lat,lng; �������� ��Ŀ���� ��ġ�� ��� �����ϴ� �����̳�.
- ���� ó�� �ε��� ��Ŀ �ϳ��� ����
- ��Ŀ�� �߰��� �� ��ġ �߰�
- ��Ŀ�� ���ŵ� �� ��ġ ����
- ��Ŀ �̵��� ���� �� �߰��� ����.
- �˻� ������� ��� �׸��� Ŭ���ϴ� ���� ���� ��ġ�� �̵�.


��Ŀ�� �����̸�, ó�� ��Ŀ ��ġ�� map_marker_positions ���� ã�Ƽ�, �������� ���� ���� ��ġ�� ġȯ.
��Ŀ�� ����Ŭ���ϸ� map_marker_positions ���� ã�Ƽ� ��Ŀ�� �����ϰ�, �ʿ����� ��Ŀ ����
������ ����Ŭ���ϸ� ����Ŭ���� ��ġ�� ��Ŀ �����ϰ� map_marker_positions ���� ��Ŀ �߰�.
*/
function map_point(i) { //�˻��� ��ġ ������ �迭���� �ε�
	center = result_array[i].geometry.location;
	map.setCenter(center);
}
function view_list() { //�˻��� ��ġ ������ �迭���� ����Ʈ�� �Ѹ�
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
}
function address_adder(results) {
	result_array = new Array();
	if(typeof(results.length) == "undefined") results = new Array(results);

	for(var i=0;i<results.length;i++) {
		if(results[i].formatted_address || results[i].formatted_address != null) {
			result_array[i] = { from: results[i].result_from,
				formatted_address: results[i].formatted_address,
				geometry: {location : new daum.maps.LatLng(results[i].geometry.lat, results[i].geometry.lng) } };
		}
	}
	view_list();
}

function getMaps() {
	var mapOption = {
		level: map_zoom,
		center: new daum.maps.LatLng(defaultlat, defaultlng)
	}
	map = new daum.maps.Map(document.getElementById("map_canvas"), mapOption);

	if(typeof(opener) !="undefined" && opener != null)
	{
		var node = opener.editorPrevNode;
	}

	if(typeof(node) !="undefined" && node && node.nodeName == "IMG") {
		var img_var = {
				'component': 'map_components',
				'method': 'decode_data',
				'style': node.getAttribute('style'),
				'data': node.getAttribute('alt')
			};
		var img_data = new Array();

		var response_tags = new Array('error','message','width','height','results');
		exec_xml('editor', 'procEditorCall', img_var, function(ret_obj,b) {
				jQuery("#width").val(parseInt(ret_obj['width'],10));
				jQuery("#height").val(parseInt(ret_obj['height'],10));

				img_data = ret_obj['results'];

				var center_split = img_data['map_center'].split(',');
				center = new daum.maps.LatLng(center_split[0], center_split[1]);
				map.setCenter(center);

				map_lat = center.getLng();
				map_lng = center.getLat();

				var markers_split = img_data['map_markers'].split(';');
				map_marker_positions = img_data['map_markers'].trim();
				marker = addMarker(0);

				map_zoom = 20 - parseInt(img_data['map_zoom'],10);
				if(!map_zoom) map_zoom = 5;
				map.setLevel(map_zoom);
			}, response_tags);
	} else {
		center = new daum.maps.LatLng(defaultlat, defaultlng);
		map.setCenter(center);
		var center = map.getCenter();

		jQuery("#width").val('600');
		jQuery("#height").val('400');
		map.setLevel(map_zoom);
	}

	var zoomControl = new daum.maps.ZoomControl();
	map.addControl(zoomControl, daum.maps.ControlPosition.LEFT);
	var mapTypeControl = new daum.maps.MapTypeControl();
	map.addControl(mapTypeControl, daum.maps.ControlPosition.TOPRIGHT);

	daum.maps.event.addListener(map, 'dblclick', function(MouseEvent) {
		latlng = MouseEvent.latLng;
		addMarker(latlng);
	});

}

/* ���ο� ��ġ�� ��Ŀ �߰�. latlng = 0 �� ���, map_marker_positions �� ������ ��Ŀ ���� ���� */
function addMarker(latlng) {
	var new_marker_obj;
	/* ��ü ������ removeMarker() �� ����*/
	// ��Ŀ �ϴ� �� ����
	if(typeof(map_markers) != "undefined") {
		for(var i = 0; i < map_markers.length; i++)
		{
			map_markers[i].setMap(null);
		}
	}

	if(latlng != 0) {
		var latitude = latlng.getLat();
		var longitude = latlng.getLng();

		// �ߺ��Ǵ� ��Ŀ�� �������� �ʵ���.
		map_marker_positions = map_marker_positions.replace(latitude+','+longitude+';', '');
		map_marker_positions += latitude + ',' + longitude + ';'; /* removeMarker() �� �ٸ� �� */
	}

	positions = makeLocationArray(map_marker_positions);

	// ��ü ��Ŀ �ٽ� ����
	for(var i = 0; i < positions.length; i++)
	{
		map_markers[i] = new daum.maps.Marker({
			position: positions[i]
		});
		map_markers[i].setMap(map);
		map_markers[i].setDraggable(true);
		map_markers[i].soo_position = positions[i];
		new_marker_obj = map_markers[i];

		// �̺�Ʈ ��� �巡�� ���۰� ���� ���� ����� ���� �Ǿ�����
		daum.maps.event.addListener(map_markers[i], "dragstart", function() {
			var position = this.soo_position;
			map_marker_positions = map_marker_positions.replace(position.getLat() + ',' + position.getLng() + ';', '');
		});
		daum.maps.event.addListener(map_markers[i], "dragend", function() {
			var position = this.getPosition();
			// �ߺ��Ǵ� ��Ŀ�� �������� �ʵ���.
			map_marker_positions = map_marker_positions.replace(position.getLat() + ',' + position.getLng() + ';', '');
			map_marker_positions += position.getLat() + ',' + position.getLng() + ';';
			addMarker(0);
		});
		daum.maps.event.addListener(map_markers[i], "rightclick", function() {
			var position = this.soo_position;
			removeMarker(position);
		});
	}

	// �߰��� ��Ŀ�� �迭�� ���� �������� �����Ŷ� ���� �Ͽ� ������ ��Ŀ ����
	return new_marker_obj;

}
function removeMarker(latlng) {
/* ��ü ������ removeMarker() �� ����*/
	// ��Ŀ �ϴ� �� ����
	for(var i = 0; i < map_markers.length; i++)
	{
		map_markers[i].setMap(null);
	}

	var latitude = latlng.getLat();
	var longitude = latlng.getLng();

	// ��Ŀ ��ġ ����
	map_marker_positions = map_marker_positions.replace(latitude+','+longitude+';', '');
	positions = makeLocationArray(map_marker_positions);

	// ��ü ��Ŀ �ٽ� ����
	for(var i = 0; i < positions.length; i++)
	{
		map_markers[i] = new daum.maps.Marker({
			position: positions[i]
		});
		map_markers[i].setMap(map);
		map_markers[i].setDraggable(true);
		map_markers[i].soo_position = positions[i];
		new_marker_obj = map_markers[i];

		// �̺�Ʈ ��� �巡�� ���۰� ���� ���� ����� ���� �Ǿ�����
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
		daum.maps.event.addListener(map_markers[i], "rightclick", function() {
			var position = this.soo_position;
			removeMarker(position);
		});
	}

}

function makeLocationArray(str_position) {
	var arr_positons = new Array();
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
	if(!width) {width = '600'}
	if(!height) {height = '400'}

	//XE���� �Ӽ� �����ϴ� �������� �ٲ�ٸ�, alt �� ����
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

	var response_tags = new Array('error','message','results');
	exec_xml('editor', 'procEditorCall', img_var, function(ret_obj,b) {
			var results = ret_obj['results']; img_data = results;

			var text = "<img src=\"https://maps-api-ssl.google.com/maps/api/staticmap?center="+map_lat+','+map_lng+"&zoom="+map_zoom+"&size="+width+"x"+height;
			var positions = map_marker_positions.split(";");
			for(var i = 0; i < positions.length; i++)
			{
				if(!positions[i].trim()) continue;
				text += "&markers=size:mid|"+positions[i];
			}
			text += "&sensor=false\" editor_component=\"map_components\" alt=\""+img_data+"\" style=\"border:2px dotted #FF0033; no-repeat center;width: "+width+"px; height: "+height+"px;\" />";

			opener.editorFocus(opener.editorPrevSrl);
			var iframe_obj = opener.editorGetIFrame(opener.editorPrevSrl)
			opener.editorReplaceHTML(iframe_obj, text);
			opener.editorFocus(opener.editorPrevSrl);
			window.close();
		}, response_tags);

}
jQuery(document).ready(function() { getMaps(); });