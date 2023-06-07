<!DOCTYPE html>
<html>

<head>
    <title>CPA Notificación</title>
</head>

<body>
    <h1>Seguimiento a solicitud de revisión</h1>
    <p>
        Se le notifica que la solicitud de revisión para la evaluación {{ $evaluacion }} de la materia
        {{ $materia }} ha sido <b>{{ $decision ? 'Aprobada' : 'Rechazada' }}</b>.
    </p>

    @if ($decision)
        <p>La solicitud de revisión para la evaluación {{$evaluacion}} ha sido aprobada.</p>
        <p>
            La revisión será realizada por el docente Ing. {{ $docente }}.
        </p>
        <p>
            En el lugar y fecha: {{ $lugar }} el {{ $fecha }}.
        </p>
    @else
        <p>La solicitud de revisión para la evaluación {{ $evaluacion }} ha sido rechazada.</p>
    @endif
    <p>
        Para revisar la solicitud, ingrese al sistema CPA.
    </p>
    <small>Correo generado automáticamente por el sistema. favor no contestar a este correo.</small>
</body>

</html>
