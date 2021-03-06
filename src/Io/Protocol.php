<?php

namespace Clue\React\Quassel\Io;

use Clue\QDataStream\Writer;
use Clue\QDataStream\Types;
use Clue\QDataStream\Reader;

class Protocol
{
    // https://github.com/quassel/quassel/blob/8e2f578b3d83d2dd7b6f2ea64d350693073ffed1/src/common/protocol.h#L30
    const MAGIC = 0x42b33f00;

    // https://github.com/quassel/quassel/blob/8e2f578b3d83d2dd7b6f2ea64d350693073ffed1/src/common/protocol.h#L32
    const TYPE_INTERNAL = 0x00;
    const TYPE_LEGACY = 0x01;
    const TYPE_DATASTREAM = 0x02;
    const TYPELIST_END = 0x80000000;

    // https://github.com/quassel/quassel/blob/8e2f578b3d83d2dd7b6f2ea64d350693073ffed1/src/common/protocol.h#L39
    const FEATURE_ENCRYPTION = 0x01;
    const FEATURE_COMPRESSION = 0x02;

    const REQUEST_INVALID = 0;
    const REQUEST_SYNC = 1;
    const REQUEST_RPCCALL = 2;
    const REQUEST_INITREQUEST = 3;
    const REQUEST_INITDATA = 4;
    const REQUEST_HEARTBEAT = 5;
    const REQUEST_HEARTBEATREPLY = 6;

    private $binary;
    private $userTypeReader;

    public function __construct(Binary $binary)
    {
        $this->binary = $binary;
        $this->types = new Types();

        $this->userTypeReader = array(
            // All required by SessionInit
            'NetworkId' => function (Reader $reader) {
                return $reader->readUInt();
            },
            'Identity' => function (Reader $reader) {
                return $reader->readVariantMap();
            },
            'IdentityId' => function (Reader $reader) {
                return $reader->readUInt();
            },
            'BufferInfo' => function (Reader $reader) {
                return array(
                    'id'      => $reader->readUInt(),
                    'network' => $reader->readUInt(),
                    'type'    => $reader->readUShort(),
                    'group'   => $reader->readUInt(),
                    'name'    => $reader->readByteArray(),
                );
            },
            // all required by "Network" InitRequest
            'Network::Server' => function (Reader $reader) {
                return $reader->readVariantMap();
            },
            // unknown source?
            'BufferId' => function(Reader $reader) {
                return $reader->readUInt();
            },
            'Message' => function (Reader $reader) {
                return array(
                    'id'         => $reader->readUInt(),
                    'timestamp'  => new \DateTime('@' . $reader->readUInt()),
                    'type'       => $reader->readUInt(),
                    'flags'      => $reader->readUChar(),
                    'bufferInfo' => $reader->readUserTypeByName('BufferInfo'),
                    'sender'     => $reader->readByteArray(),
                    'content'    => $reader->readByteArray()
                );
            },
            'MsgId' => function (Reader $reader) {
                return $reader->readUInt();
            }
        );
    }

    public function writeVariantList(array $list)
    {
        $writer = new Writer(null, $this->types);
        $writer->writeType(Types::TYPE_VARIANT_LIST);
        $writer->writeVariantList($list);

        return (string)$writer;
    }

    public function writeVariantMap(array $map)
    {
        // TODO: datastream protocol uses UTF-8 keys..
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L80
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/datastream/datastreampeer.cpp#L109

        $writer = new Writer(null, $this->types);
        $writer->writeType(Types::TYPE_VARIANT_MAP);
        $writer->writeVariantMap($map);

        return (string)$writer;
    }

    public function writePacket($packet)
    {
        // TODO: legacy compression / decompression
        // legacy protocol writes variant via DataStream to ByteArray
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/legacy/legacypeer.cpp#L105
        // https://github.com/quassel/quassel/blob/master/src/common/protocols/legacy/legacypeer.cpp#L63
        //$data = $this->types->writeByteArray($data);

        // raw data is prefixed with length, then written
        // https://github.com/quassel/quassel/blob/master/src/common/remotepeer.cpp#L241
        return $this->binary->writeUInt32(strlen($packet)) . $packet;
    }

    public function readVariant($packet)
    {
        $reader = Reader::fromString($packet, $this->types, $this->userTypeReader);

        return $reader->readVariant();
    }
}
