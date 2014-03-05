<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>Geocodificación</title>

<?php
        require("config.php");
		
		function generateJSArray($list)
		{
			$JSArray = 'new Array(';
			$total = count($list);
			
			if ($total == 0)
			{
				return 'new Array()';
			}
			else
			{
				for ($i=0; $i<$total; $i++)
				{
					$JSArray .= $list[$i].',';
				}
				return substr($JSArray, 0, strlen($JSArray)-1).')';
			}
		}
		
		$output = '';
		$markerListLat = array();
		$markerListLng = array();
		
        $connection = mysql_connect('localhost', BBDD_USER, BBDD_PASSWD);
        if (!$connection) { die("Error en la conexión a la BBDD: " . mysql_error()); }
        
        $db_selected = mysql_select_db(BBDD_NAME, $connection);
        if (!$db_selected) { die("Error en el acceso a la BBDD: " . mysql_error()); }
        
        $query = 'SELECT * FROM tplace WHERE 1 ORDER BY id ASC';
        $result = mysql_query($query);
        if (!$result) { die("Error en la consulta a la BBDD: " . mysql_error()); }
        
        
        $delay = 0;
        $base_url = "http://".MAPS_HOST."/maps/geo?output=csv&key=".KEY;
        
        $output = '<dl>';
        while ($row = @mysql_fetch_assoc($result)) 
        {
            $geocode_pending = true;
            
            while ($geocode_pending) 
            {
                $id = $row["id"];
                $address = utf8_decode($row['direccion']).', '.utf8_decode($row['localidad']).', '.utf8_decode($row['codigo_postal']);
                
                $request_url = $base_url."&q=".urlencode($address);
                $csv = file_get_contents($request_url) or die("url not loading");
                
                $csvLine = explode(",", $csv);
                $status = $csvLine[0];
                $lat = $csvLine[2];
                $lng = $csvLine[3];
				
				array_push($markerListLat, $lat);
				array_push($markerListLng, $lng);
                
                if (strcmp($status, STATUS_OK) == 0) 
                {
                    // EXITO EN LA GEOCODIFICACION
                    $geocode_pending = false;
                    
					// Actualizamos los valores de la BBDD
                    $query = sprintf("UPDATE tplace " .
                         " SET latitud = '%s', longitud = '%s' " .
                         " WHERE id = %s LIMIT 1;",
                         mysql_real_escape_string($lat),
                         mysql_real_escape_string($lng),
                         mysql_real_escape_string($id));
                         
                    $update_result = mysql_query($query);
                    if (!$update_result) 
					{
						die("SQL Query errónea: " . mysql_error()); 
					}
                    else
                    {
                        $output .= '<dt>ÉXITO > geocodificando la siguiente direccion: '.$address.'</dt>';
                        $output .= '<dd>(Lat, Lng) > ('.$lat.', '.$lng.')</dd>';
                    }
                } 
                else if (strcmp($status, STATUS_KO_TIME) == 0) 
                {
                    // PETICIÓN DEMASIADO RÁPIDA
                    $delay += DELAY_TIME;
                } 
                else 
                {
                    // FALLO EN LA GEOCODIFICACIÓN
                    $geocode_pending = false;
					
					array_pop($markerListLat);
					array_pop($markerListLng); 
						
                    $output .= '<dt>ERROR > geocodificando la siguiente direccion: '.$address.'</dt>';
                    $output .= '<dd>ESTADO DE ERROR > '.$status.'</dd>';
                }
                
                usleep($delay);
            }
        }
        $output .= '</dl>';
	
?>

<script type="text/javascript" src="http://maps.google.com/maps/api/js?sensor=false"></script>
<script type="text/javascript">

function initialize() 
{
	var marker;
	var myLatLng;
	var total = 0;
	var i = 0;
	var latlng = new google.maps.LatLng(<?php echo SPAIN_LAT; ?>, <?php echo SPAIN_LNG; ?>);
	var myOptions = {
		zoom: <?php echo SPAIN_ZOOM; ?>,
		center: latlng,
		mapTypeId: google.maps.MapTypeId.ROADMAP
	};

	var map = new google.maps.Map(document.getElementById("map_canvas"), myOptions);
	
	var markerListLat = <?php echo generateJSArray($markerListLat); ?>;
	var markerListLng = <?php echo generateJSArray($markerListLng); ?>;
	
	total = markerListLat.length;
	for (i=0; i<total; i++)
	{
		myLatlng = new google.maps.LatLng(markerListLat[i], markerListLng[i]);
		
		marker = new google.maps.Marker({
			position: myLatlng, 
			map: map, 
			title: ("Punto ID: "+i)
		}); 
	}
	
}
</script>

</head>

<body onload="initialize();">
    <h1>Geocodificación de Base de Datos</h1>
    <p><?php echo $output; ?></p>
    <div id="map_canvas" style="width:700px; height:500px; text-align:center;"></div>
</body>
</html>