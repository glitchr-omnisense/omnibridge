<?php

namespace Omnibridge\Persistence\Lifecycle;

/** When a flush has computed what it writes, before it writes it: a listener may persist, remove or change entities, then recompute them. */
final class OnFlush extends FlushEvent
{
}
