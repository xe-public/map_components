// 사용자 위치 마커 배열
var user_markers = [];
// 사용자 위치 마커 이미지
var user_image = [];

function addMarker(target_map, map_marker_positions) {
	positions = makeLocationArray(map_marker_positions);

	// 전체 마커 생성
	for(var i = 0; i < positions.length; i++)
	{
		var markers = new daum.maps.Marker({
			position: positions[i]
		});
		markers.setMap(target_map);
		markers.setDraggable(false);
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

// 모든 지도에 같은 마커를 표시하는 함수입니다
function displayMarkerAllMaps(locPosition) {
	ggl_map.forEach(function(target_map, key) {

		if(typeof(user_markers[key]) == "undefined") {
			user_markers[key] = new daum.maps.Marker({
				position: locPosition,
				image: user_image,
			});
			user_markers[key].setMap(target_map);
			user_markers[key].setDraggable(false);
		}
		else{
			user_markers[key].setPosition(locPosition);
		}
	});
}

jQuery(document).ready(function() {
	if(map_component_user_position == 'Y') {
		// 사용자 위치 마커 이미지
		user_image = new daum.maps.MarkerImage(
						request_uri + './modules/editor/components/map_components/front/images/person.png',
						new daum.maps.Size(30, 30),
						{
							coords: '15, 15, 15',
							shape: 'circle',
							offset: new daum.maps.Point(15, 15),
							spriteOrigin : new daum.maps.Point(0, 0),
						}
					);
		// HTML5의 geolocation으로 사용할 수 있는지 확인합니다
		if ("geolocation" in navigator) {
			var geo_options = {
				enableHighAccuracy: true,
				maximumAge        : 0,
				timeout           : 27000
			};

			// GeoLocation을 이용해서 접속 위치를 얻어옵니다
			navigator.geolocation.watchPosition(function(position) {

				var lat = position.coords.latitude, // 위도
					lon = position.coords.longitude; // 경도

				var locPosition = new daum.maps.LatLng(lat, lon); // 마커가 표시될 위치를 geolocation으로 얻어온 좌표로 생성합니다.

				// 마커를 표시합니다
				displayMarkerAllMaps(locPosition);

			  });

		}
	}
});

