<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

<?php
// Verifica si el parámetro 'app' está en la URL
if(isset($_GET['app'])) {
    // Obtiene el valor del parámetro 'app'
    $appParam = $_GET['app'];
    // Muestra el valor del parámetro en un alert usando JavaScript
    echo "<script>alert('Valor de PHP app: " . htmlspecialchars($appParam) . "');</script>";
} else {
    echo "<script>alert('El parámetro app no está presente en la URL');</script>";
}
?>


    <h1 id="evento">EJECUTA EL EVENTO</h1>
    
    <form id="uploadForm" action="https://app-api.modatex.com.ar/pruebaWebview" method="post" enctype="multipart/form-data">
        <input type="file" id="archivo" name="archivo" accept=".txt, .pdf, .docx, .xlsx, image/*" capture> <br><br>
        <button type="submit">Subir Archivo</button>
    </form>
    
    <br>
    <a href="https://app-api.modatex.com.ar/envio_gratis.png" download="true">Haz clic aquí para descargar el archivo</a>

    <p id="filePath"></p> <!-- Nueva etiqueta para mostrar la ruta del archivo -->

    <script>
    
       
    // Obtener el query string de la URL
    var queryString = window.location.search;
    
    // Crear un objeto URLSearchParams para manejar los parámetros
    var urlParams = new URLSearchParams(queryString);
    
    // Obtener el valor del parámetro 'app'
    var appParam = urlParams.get('app');
    
    // Mostrar el valor del parámetro en un alert
    if (appParam) {
        alert('Valor de app: ' + appParam);
    } else {
        alert('El parámetro app no está presente en la URL');
    }


        const evento = document.querySelector('#evento');
        const archivoInput = document.querySelector('#archivo');
        const filePathDisplay = document.querySelector('#filePath');

        function sendMessageToFlutter() {
            if (window.FlutterChannel) {
                window.FlutterChannel.postMessage("nombre_del_evento"); // Mensaje enviado a Flutter
            } else {
                console.log("FlutterChannel no está disponible.");
            }
        }

        function showFilePath() {
            if (archivoInput.files.length > 0) {
                const filePath = archivoInput.value;
                filePathDisplay.textContent = "Ruta del archivo: " + filePath;
            } else {
                filePathDisplay.textContent = "";
            }
        }

        archivoInput.addEventListener('change', showFilePath);
        evento.addEventListener('click', sendMessageToFlutter);
    </script>
</body>
</html>
