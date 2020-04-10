# 安装

```php
$ composer required sbing/ali-oss
```

# 使用

config 目录中找到 filesystems.php 文件， 添加以下配置：

```php
'oss' => [
    'driver' => 'oss',
    'access_id' => env('OSS_ACCESS_ID'),
    'access_key' => env('OSS_ACCESS_KEY'),
    'bucket' => env('OSS_BUCKET'),
    'endpoint' => env('OSS_ENDPOINT', null),
    'host' => env('OSS_HOST', null),
]
```
