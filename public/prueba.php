<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1 id="evento">EJECUTA EL EVENTO</h1>

    <script>
        const evento = document.querySelector('#evento')

        function sendMessageToFlutter() {
            if (window.FlutterChannel) {
                window.FlutterChannel.postMessage("nombre_del_evento"); // Mensaje enviado a Flutter
            } else {
                console.log("FlutterChannel no está disponible.");
            }
        }

        evento.addEventListener('click',()=>{
            sendMessageToFlutter()
        })
    </script>
</body>
</html>