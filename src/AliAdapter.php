<?php

namespace Sbing\AliOss;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Config;
use League\Flysystem\Util;
use OSS\Core\OssException;
use OSS\Model\ObjectInfo;
use OSS\Model\PrefixInfo;
use OSS\OssClient;

class AliAdapter extends AbstractAdapter
{
    /**
     * @var OssClient
     */
    protected $client;

    /**
     * @var array
     */
    protected $bucket;

    /**
     * @var string
     */
    protected $host;

    /**
     * AliAdapter constructor.
     *
     * @param  OssClient  $client
     * @param  array  $config
     */
    public function __construct(OssClient $client, array $config)
    {
        $this->client = $client;

        $this->bucket = $config['bucket'];

        $this->host = $config['host'];
    }

    /**
     * @param $path
     * @return string
     */
    public function getUrl($path)
    {
        if (Str::startsWith($path, 'http')) {
            return $path;
        }

        return rtrim($this->host, '/').'/'.ltrim($path, '/');
    }

    /**
     * @inheritDoc
     */
    public function write($path, $contents, Config $config)
    {
        $this->client->putObject($this->bucket, $path, $contents, [
            OssClient::OSS_CHECK_MD5 => true,
            OssClient::OSS_CONTENT_TYPE => Util::guessMimeType($path, null)
        ]);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function writeStream($path, $resource, Config $config)
    {
        $contents = stream_get_contents($resource);

        return $this->write($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function update($path, $contents, Config $config)
    {
        return $this->write($path, $contents, $config);
    }

    /**
     * @inheritDoc
     */
    public function updateStream($path, $resource, Config $config)
    {
        return $this->writeStream($path, $resource, $config);
    }

    /**
     * @inheritDoc
     */
    public function rename($path, $newpath)
    {
        if (!$this->copy($path, $newpath)) {
            return false;
        }

        return $this->delete($path);
    }

    /**
     * @inheritDoc
     */
    public function copy($path, $newpath)
    {
        try {
            $this->client->copyObject($this->bucket, $path, $this->bucket, $newpath);

            return true;
        } catch (OssException $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function delete($path)
    {
        $this->client->deleteObject($this->bucket, $path);

        return !$this->has($path);
    }

    /**
     * @inheritDoc
     */
    public function deleteDir($dirname)
    {
        $data = $this->listContents($dirname, true);

        $files = Collection::make($data)->pluck('path')->toArray();

        $this->client->deleteObjects($this->bucket, $files);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function createDir($dirname, Config $config)
    {
        $this->client->createObjectDir($this->bucket, $dirname);

        return ['path' => $dirname, 'type' => 'dir'];
    }

    /**
     * @inheritDoc
     */
    public function setVisibility($path, $visibility)
    {
        try {
            $visibility = $visibility === AdapterInterface::VISIBILITY_PUBLIC
                ? OssClient::OSS_ACL_TYPE_PUBLIC_READ
                : OssClient::OSS_ACL_TYPE_PRIVATE;

            return $this->client->putObjectAcl($this->bucket, $path, $visibility);
        } catch (OssException $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function has($path)
    {
        return $this->client->doesObjectExist($this->bucket, $path);
    }

    /**
     * @inheritDoc
     */
    public function read($path)
    {
        $contents = $this->client->getObject($this->bucket, $path);

        return ['type' => 'file', 'path' => $path, 'contents' => $contents];
    }

    /**
     * @inheritDoc
     */
    public function readStream($path)
    {
        $content = $this->client->getObject($this->bucket, $path);

        return ['type' => 'file', 'path' => $path, 'stream' => $content];
    }

    /**
     * @inheritDoc
     */
    public function listContents($directory = '', $recursive = false): array
    {
        try {
            $info = $this->client->listObjects($this->bucket, [
                'prefix' => $directory,
            ]);

            $files = Collection::wrap($info->getObjectList())->map(function (ObjectInfo $info) {
                return [
                    'type' => Str::endsWith($info->getKey(), '/') ? 'dir' : 'file',
                    'path' => $info->getKey(),
                    'size' => $info->getSize(),
                    'timestamp' => Carbon::parse($info->getLastModified())->timestamp
                ];
            })->all();

            if ($recursive === false) {
                return $files;
            }

            $fs = Collection::wrap($info->getPrefixList())->map(function (PrefixInfo $info) use ($recursive) {
                return $this->listContents($info->getPrefix(), $recursive);
            })->flatten(1)->all();

            return array_merge($files, $fs);
        } catch (OssException $e) {
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function getMetadata($path)
    {
        return $this->client->getObjectMeta($this->bucket, $path);
    }

    /**
     * @inheritDoc
     */
    public function getSize($path)
    {
        $arr = $this->getMetadata($path);

        return [
            'size' => $arr['content-length']
        ];
    }

    /**
     * @inheritDoc
     */
    public function getMimetype($path)
    {
        $arr = $this->getMetadata($path);

        return [
            'mimetype' => $arr['content-type']
        ];
    }

    /**
     * @inheritDoc
     */
    public function getTimestamp($path)
    {
        $arr = $this->getMetadata($path);

        return [
            'timestamp' => Carbon::parse($arr['last-modified'])->timestamp
        ];
    }

    /**
     * @inheritDoc
     */
    public function getVisibility($path)
    {
        try {
            $visibility = $this->client->getObjectAcl($this->bucket, $path) === OssClient::OSS_ACL_TYPE_PRIVATE
                ? AdapterInterface::VISIBILITY_PRIVATE
                : AdapterInterface::VISIBILITY_PUBLIC;
        } catch (OssException $e) {
            $visibility = AdapterInterface::VISIBILITY_PRIVATE;
        }

        return compact('visibility');
    }
}
