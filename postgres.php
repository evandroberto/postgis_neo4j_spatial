<?php
    $conexao = pg_connect("dbname=transporte port=5432 host=localhost user=postgres password=postgres");

    if ($conexao) {
        //echo "Conectado com: " . pg_host($conexao) . "<br/> ";
    }
    else {
        echo pg_last_error($conexao);
        exit;
    }

    //CONSULTA DISTANCIA
    $result = pg_query($conexao, 
                   "SELECT gid, name, ST_AsGeoJSON(geom) 
                    FROM municipios 
                    WHERE ST_Distance_Sphere(geom, ST_MakePoint(-47.9304084722222, -15.7805194722222)) <= 100000");
    if (!$result) {
        echo "Erro na consulta.<br>";
        exit;
    }

    $dist = array(
        'type'      => 'FeatureCollection',
        'features'  => array()
    );

    $i=1;
    while ($row = pg_fetch_row($result)) {
        $json = json_decode($row[2]);

        $coords_x = $json->coordinates[0];
        $coords_y = $json->coordinates[1];

        $feature = array(
            'type' => 'Feature',
            'geometry' => array(
                'type' => 'Point',
                'coordinates' => array(
                    $coords_x,
                    $coords_y
                )
            ),
            'properties' => array(
                'marker-size' => "large",
                'marker-color' => "#FF3300",
                'marker-symbol' => $i,
                'title' => $row[1]
            )
        );
        $i++;
        # Add feature arrays to feature collection array
        array_push($dist['features'], $feature);
    }

    //CONSULTA PROXIMIDADE
    $result = pg_query($conexao, 
                   "SELECT gid, name, ST_AsGeoJSON(geom) 
                    FROM municipios 
                    WHERE ST_DWithin(geom, ST_MakePoint(-47.9304084722222, -15.7805194722222), 100000)
                    ORDER BY ST_Distance(geom, ST_MakePoint(-47.9304084722222, -15.7805194722222))
                    LIMIT 2");
    if (!$result) {
        echo "Erro na consulta.<br>";
        exit;
    }

    $prox = array(
        'type'      => 'FeatureCollection',
        'features'  => array()
    );

    while ($row = pg_fetch_row($result)) {
        $json = json_decode($row[2]);

        $coords_x = $json->coordinates[0];
        $coords_y = $json->coordinates[1];

        $feature = array(
            'type' => 'Feature',
            'geometry' => array(
                'type' => 'Point',
                'coordinates' => array(
                    $coords_x,
                    $coords_y
                )
            ),
            'properties' => array(
                'marker-size' => "large",
                'marker-color' => "#FF9900",
                'marker-symbol' => "star",
                'title' => $row[1]
            )
        );
        # Add feature arrays to feature collection array
        array_push($prox['features'], $feature);
    }

    //CONSULTA BOUNDING BOX
    $result = pg_query($conexao, 
                   "SELECT gid, name, ST_AsGeoJSON(geom) 
                    FROM municipios 
                    WHERE geom @ ST_MakeEnvelope(-48.339843750025791, -16.105195094166007, -47.248077392578125, -15.444414528150116)");
    if (!$result) {
        echo "Erro na consulta.<br>";
        exit;
    }

    $bbox = array(
        'type'      => 'FeatureCollection',
        'features'  => array()
    );

    while ($row = pg_fetch_row($result)) {
        $json = json_decode($row[2]);

        $coords_x = $json->coordinates[0];
        $coords_y = $json->coordinates[1];

        $feature = array(
            'type' => 'Feature',
            'geometry' => array(
                'type' => 'Point',
                'coordinates' => array(
                    $coords_x,
                    $coords_y
                )
            ),
            'properties' => array(
                'marker-size' => "large",
                'marker-color' => "#FF6600",
                'marker-symbol' => "city",
                'title' => $row[1]
            )
        );
        # Add feature arrays to feature collection array
        array_push($bbox['features'], $feature);
    }
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset=utf-8 />
    <title>Evandro Roberto - Projeto Final - PostGIS</title>
    <meta name='viewport' content='initial-scale=1,maximum-scale=1,user-scalable=no'/>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>
    <script src='https://api.mapbox.com/mapbox.js/v2.2.3/mapbox.js'></script>
    <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
    <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-draw/v0.2.3/leaflet.draw.css' rel='stylesheet' />
    <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-draw/v0.2.3/leaflet.draw.js'></script>
    <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-geodesy/v0.1.0/leaflet-geodesy.js'></script>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet">
    <link href='https://api.mapbox.com/mapbox.js/v2.2.3/mapbox.css' rel='stylesheet' />
    <link href="style.css" rel="stylesheet" type="text/css">
    <link href='https://api.mapbox.com/mapbox.js/plugins/leaflet-draw/v0.2.3/leaflet.draw.css' rel='stylesheet' />
    <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-draw/v0.2.3/leaflet.draw.js'></script>
    <script src='https://api.mapbox.com/mapbox.js/plugins/leaflet-geodesy/v0.1.0/leaflet-geodesy.js'></script>
</head>
<body>
<div id="desc">
    <div class="top-row">
        <h3 class="heading">PostGIS + Dados Geográficos</h3>
    </div>
</div>
<div id='map'></div>

<div class="zoom-box prose">
    <a href="#">
        <div id="central-zoom" class="fake-button" onclick="changeZoom(this.id)" data-zoom="4"
        data-position="-15.7782, -52.8395">Brasil
        </div>
    </a>
    <a href="#">
        <div id="corpus-zoom" class="fake-button" onclick="changeZoom(this.id)" data-zoom="7" 
        data-position="-15.9495904,-49.2822898">Goiás
        </div>
    </a>
    <a href="#">
        <div id="clear" class="fake-button" onclick="changeZoom(this.id)" data-zoom="10" 
        data-position="-15.77, -47.8">Distrito Federal
        </div>
    </a>
    <a href="#">
        <div id="dist" class="fake-button op" onclick="changeZoom(this.id)" data-zoom="9" 
        data-position="-15.57, -47.8">Distância
        </div>
    </a>
    <a href="#">
        <div id="prox" class="fake-button op" onclick="changeZoom(this.id)" data-zoom="10" 
        data-position="-15.77, -47.8">Proximidade
        </div>
    </a>
    <a href="#">
        <div id="bbox" class="fake-button op" onclick="changeZoom(this.id)" data-zoom="10" 
        data-position="-15.77, -47.8">Bounding Box
        </div>
    </a>
</div>

<script>
    L.mapbox.accessToken = 'pk.eyJ1IjoiZXZhbmRyb2JlcnRvIiwiYSI6ImNpbWpjajVoZTAwbzd2Zmt1OWU5NTQ0eG4ifQ.hV98g175SlkvpoAonpNTwg';
    var map              = L.mapbox.map('map', 'mapbox.light').setView([-15.7782, -52.8395], 4);
    var layerMunicipios  = L.mapbox.featureLayer().addTo(map);
    var featureGroup     = L.featureGroup().addTo(map);

    L.control.layers({
        'Mapbox Streets': L.mapbox.tileLayer('mapbox.light'),
        'Mapbox Light': L.mapbox.tileLayer('mapbox.streets').addTo(map)
    }).addTo(map);

    var drawControl = new L.Control.Draw({
        edit: {
            featureGroup: featureGroup
        },
        draw: {
            polygon: false,
            polyline: false,
            rectangle: true,
            circle: false,
            marker: true
        }
    }).addTo(map);

    map.on('draw:created', showPoints);

    function showPoints(e) {
        //clearMap(map);
        featureGroup.clearLayers();
        featureGroup.addLayer(e.layer);
    }

    layerMunicipios.on('mouseover', function(e) {
        e.layer.openPopup();
    });

    layerMunicipios.on('mouseout', function(e) {
        e.layer.closePopup();
    });

    var dist = <?php echo json_encode($dist, JSON_NUMERIC_CHECK); ?>;
    var prox = <?php echo json_encode($prox, JSON_NUMERIC_CHECK); ?>;
    var bbox = <?php echo json_encode($bbox, JSON_NUMERIC_CHECK); ?>;

    $("#clear").click(function() {
        clearMap(map);
        map.eachLayer(function(layer) {
            if (layer instanceof L.Marker) {
                map.removeLayer(layer);
            }
        });
    });

    $("#dist").click(function() {
        clearMap(map);
        featureGroup.clearLayers();
        layerMunicipios.setGeoJSON(dist);
    });

    $("#prox").click(function() {
        clearMap(map);
        featureGroup.clearLayers();
        layerMunicipios.setGeoJSON(prox);
        var polyline = L.polyline([[-15.780519472222, -47.930408472222],[-16.06652, -47.97941]]).addTo(map);
    });

    $("#bbox").click(function() {
        clearMap(map);
        featureGroup.clearLayers();
        layerMunicipios.setGeoJSON(bbox);
        var bounds = [[-16.105195094166007, -48.339843750025791], [-15.444414528150116, -47.248077392578125]];
        L.rectangle(bounds, {color: "#FF9933", weight: 2}).addTo(map);
    });

    $('.fake-button').click(function() {
        var position = this.getAttribute('data-position');
        var zoom = this.getAttribute('data-zoom');
        if (position && zoom) {
            var point = position.split(',');
            zoom = parseInt(zoom);
            map.setView(point, zoom, {animation: true});
            return false;
        }
    });

    function clearMap(m) {
        for(var i in m._layers) {
            if(m._layers[i]._path != undefined) {
                try {
                    m.removeLayer(m._layers[i]);
                }
                catch(e) {
                    console.log("problem with " + e + m._layers[i]);
                }
            }
        }
    }

</script> 

</body>
</html>