<?php

namespace LaravelRest\Http\Services\Msgpack\Extensions;

use Carbon\Carbon;
use MessagePack\BufferUnpacker;
use MessagePack\Extension;
use MessagePack\Packer;

class CarbonExtension implements Extension
{
    private $type;

    public function __construct(int $type)
    {
        if ($type < 0 || $type > 127) {
            throw new \OutOfRangeException(
                "Extension type is expected to be between 0 and 127, $type given"
            );
        }

        $this->type = $type;
    }

    public function getType() : int
    {
        return $this->type;
    }

    public function pack(Packer $packer, $value) : ?string
    {
        if (!$value instanceof Carbon) {
            return null;
        }

        return $packer->packExt($this->type, $value->format('d.m.Y H:i:s'));
    }

    public function unpackExt(BufferUnpacker $unpacker, int $extLength)
    {
        return Carbon::createFromFormat('d.m.Y H:i:s', $unpacker->read($extLength));
    }
}