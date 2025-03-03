<?php

namespace LaravelRest\Http\Services\Msgpack\Extensions;

use Carbon\Carbon;
use MessagePack\Extension;
use MessagePack\BufferUnpacker;
use MessagePack\Packer;

class CarbonExtension implements Extension
{
    private $type = 42;

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