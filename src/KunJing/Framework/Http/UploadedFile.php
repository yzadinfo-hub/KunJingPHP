<?php
/**
 * KunJingPHP (堃景) — 自研 PHP 框架
 * 模块：上传文件封装（净室实现）
 *
 * 用法：
 *  $file = $request->file('avatar');
 *  if ($file && $file->isValid()) {
 *      $path = $file->store('uploads/avatar');   // 返回相对路径
 *  }
 */

namespace KunJing\Framework\Http;

use RuntimeException;

class UploadedFile
{
    protected string $originalName;
    protected string $mimeType;
    protected string $tmpPath;
    protected int $error;
    protected int $size;
    protected ?string $storedPath = null;

    public function __construct(array $info)
    {
        $this->originalName = (string) ($info['name'] ?? '');
        $this->mimeType     = (string) ($info['type'] ?? '');
        $this->tmpPath      = (string) ($info['tmp_name'] ?? '');
        $this->error        = (int) ($info['error'] ?? UPLOAD_ERR_NO_FILE);
        $this->size         = (int) ($info['size'] ?? 0);
    }

    /**
     * 由 $_FILES 的单元素结构构造（可能是多文件数组）。
     * @return array<string,self>|self|null
     */
    public static function normalize($info)
    {
        if (!is_array($info)) {
            return null;
        }
        // 多文件：name 本身是数组
        if (isset($info['name']) && is_array($info['name'])) {
            $files = [];
            foreach ($info['name'] as $index => $name) {
                $files[$index] = new self([
                    'name'     => $name,
                    'type'     => $info['type'][$index] ?? '',
                    'tmp_name' => $info['tmp_name'][$index] ?? '',
                    'error'    => $info['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $info['size'][$index] ?? 0,
                ]);
            }
            return $files;
        }
        return new self($info);
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && $this->tmpPath !== '' && is_uploaded_file($this->tmpPath);
    }

    /**
     * 测试环境（非真实 SAPI 上传）下用该方法放宽校验。
     */
    public function isReachable(): bool
    {
        return $this->error === UPLOAD_ERR_OK && $this->tmpPath !== '' && is_file($this->tmpPath);
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getErrorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK         => '上传成功',
            UPLOAD_ERR_INI_SIZE   => '文件超过 upload_max_filesize 限制',
            UPLOAD_ERR_FORM_SIZE  => '文件超过表单限制',
            UPLOAD_ERR_PARTIAL    => '文件仅部分上传',
            UPLOAD_ERR_NO_FILE    => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '缺少临时目录',
            UPLOAD_ERR_CANT_WRITE => '写入磁盘失败',
            UPLOAD_ERR_EXTENSION  => '扩展阻止了上传',
            default               => '未知上传错误',
        };
    }

    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    public function getClientOriginalExtension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function getClientMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * 真实 MIME 检测（优先 fileinfo，回退到 finfo_open 不可用时使用客户端值）。
     */
    public function getMimeType(): string
    {
        if (!is_file($this->tmpPath)) {
            return $this->mimeType;
        }
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $this->tmpPath);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($this->tmpPath);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
        return $this->mimeType;
    }

    public function getRealPath(): string
    {
        return $this->tmpPath;
    }

    public function getContents(): string
    {
        if (!is_file($this->tmpPath)) {
            return '';
        }
        return (string) file_get_contents($this->tmpPath);
    }

    /**
     * 生成安全的随机文件名（保留扩展名）。
     */
    public function hashName(?string $extension = null): string
    {
        $ext = $extension ?? $this->guessExtension();
        return md5(uniqid('', true) . microtime(true)) . ($ext !== '' ? '.' . $ext : '');
    }

    /**
     * 由真实 MIME 推测扩展名，避免信任客户端后缀。
     */
    public function guessExtension(): string
    {
        $map = [
            'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png',
            'image/gif' => 'gif', 'image/webp' => 'webp', 'image/bmp' => 'bmp',
            'application/pdf' => 'pdf', 'application/zip' => 'zip',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt', 'text/csv' => 'csv', 'application/json' => 'json',
        ];
        return $map[$this->getMimeType()] ?? $this->getClientOriginalExtension();
    }

    /**
     * 移动到指定完整路径。
     */
    public function move(string $target, bool $checkUploaded = true): bool
    {
        $dir = dirname($target);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $ok = $checkUploaded
            ? @move_uploaded_file($this->tmpPath, $target)
            : @rename($this->tmpPath, $target);
        if (!$ok && is_file($this->tmpPath)) {
            $ok = @copy($this->tmpPath, $target);
        }
        if ($ok) {
            $this->storedPath = $target;
        }
        return $ok;
    }

    /**
     * 保存到目录，返回相对路径。
     * @param string $directory 目标目录（相对或绝对）
     */
    public function store(string $directory, ?string $fileName = null, bool $checkUploaded = true): string
    {
        $name = $fileName ?? $this->hashName();
        $target = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (!$this->move($target, $checkUploaded)) {
            throw new RuntimeException('文件保存失败：' . $this->getErrorMessage());
        }
        return $target;
    }

    public function getStoredPath(): ?string
    {
        return $this->storedPath;
    }

    /**
     * 扩展名白名单校验。
     * @param array<int,string> $extensions
     */
    public function hasExtension(array $extensions): bool
    {
        return in_array(strtolower($this->guessExtension()), array_map('strtolower', $extensions), true);
    }

    /**
     * 体积上限校验（字节）。
     */
    public function isSmallerThan(int $bytes): bool
    {
        return $this->size <= $bytes;
    }
}
