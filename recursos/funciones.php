<?php
// recursos/funciones.php

function rolActual() {
    return $_SESSION['rol_activo'] ?? $_SESSION['rol'] ?? 'Invitado';
}

function tieneRol($rol) {
    return rolActual() == $rol;
}

function tieneAlgunoDeEstosRoles($roles) {
    return in_array(rolActual(), $roles);
}
?>