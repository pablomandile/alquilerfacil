<?php

namespace App\Enums;

/**
 * Un enum del dominio que sabe darse un nombre legible.
 *
 * La etiqueta vive en el enum para que la pantalla, la base y los reportes
 * hablen siempre de lo mismo.
 */
interface Etiquetable
{
    public function label(): string;
}
