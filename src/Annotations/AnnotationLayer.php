<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Annotations;

/**
 * Z-order bucket an annotation slots into when the chart composes its render
 * tree. `BehindData` sits between the grid/axes and the data marks; `OverData`
 * sits above every data mark so leader lines and callouts stay legible.
 */
enum AnnotationLayer
{
    case BehindData;
    case OverData;
}
