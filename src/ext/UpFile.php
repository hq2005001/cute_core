<?php
/**
 * Created by PhpStorm.
 * User: hq
 * Date: 18-7-3
 * Time: 上午10:10
 */

namespace Cute\ext;


use Cute\exceptions\FileUploadException;
use Cute\Service;

class UpFile extends Service
{
    /**
     * 配置
     * @var array
     */
    protected $configs = [
        'size' => 1024, // KB
        'type' => '.jpg.png.gif',
        'dir' => '/upload/files', // 文件保持目录，注意不包含static，文件会自动存入static目录，上传文件必须位于顶级目录下的static
        'splitDate' => '', // 按时间分割文件夹格式，为空不分割 'YmdH'
    ];

    /**
     * 文件头对应类型
     * @var array
     */
    protected $types = [
        '7790' => 'exe',
        '8075' => 'zip',
        '8297' => 'rar',
        '255216' => 'jpg',
        '7173' => 'gif',
        '6677' => 'bmp',
        '13780' => 'png',
        '-1-40' => 'jpg',
        '-11980' => 'png'
    ];

    /**
     * 最大大小 KB
     * @var int
     */
    protected $maxSize = null;

    /**
     * 相对目录地址
     * @var string
     */
    protected $pathDir = null;

    /**
     * 允许类型
     * @var array
     */
    protected $acceptTypes = null;

    /**
     * 设置要处理的上传文件
     * @var array
     */
    protected $files = [];

    /**
     * 文件名前缀
     * @var string
     */
    protected $prefix = '';

    /**
     * 日期分割文件夹格式 YmdH
     * @var string
     */
    protected $splitDate = null;

    /**
     * 是否写入到临时文件夹
     * @var boolean
     */
    protected $inTemp = false;

    /**
     * 产生错误的文件数量
     * @var int
     */
    protected $errNum = 0;

    /**
     * 初始化
     */
    public function __construct() {
        $conf = $this->config();
        $this->options($conf);
    }

    /**
     * 配置上传参数
     * @param array $key
     * @param mixed $default
     * @return $this
     */
    public function options($options) {
        if (!empty($options['size']))
            $this->maxSize = $options['size'];
        if (!empty($options['type']))
            $this->acceptTypes = explode('.', trim($options['type'], '.'));
        if (!empty($options['dir']))
            $this->pathDir = $options['dir'];
        if (!empty($options['splitDate']))
            $this->splitDate = $options['splitDate'];
        return $this;
    }

    /**
     * 设置要处理的上传文件
     * @param array $files
     */
    public function file($files) {
        if (isset($files['error']) && !is_array($files['error'])) {
            $files = [$files];
        } elseif (isset($files['error'])) {
            // 转换POST多文件上传数据
            $nfiles = [];
            $len = count($files['name']);
            for ($i = 0; $i < $len; $i++) {
                $items = [];
                foreach ($files as $field => $vals) {
                    $items[$field] = $vals[$i];
                }
                $nfiles[] = $items;
            }
            $files = $nfiles;
        }
        $this->files = $files;
        $this->errNum = 0;
        return $this;
    }

    /**
     * 设置文件名前缀
     * @param string $prefix
     * @return $this
     */
    public function prefix($prefix) {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * 设置允许最大大小 KB
     * @param int $value
     * @return $this
     */
    public function maxSize($value) {
        $this->maxSize = $value;
        return $this;
    }

    /**
     * 设置允许的文件类型 .jpg.png
     * @param array $value
     * @return $this
     */
    public function acceptType($value) {
        $this->acceptTypes = is_array($value) ? $value : explode('.', trim($value, '.'));
        return $this;
    }

    /**
     * 设置文件相对保存
     * @param string $value
     * @return $this
     */
    public function pathDir($value) {
        $this->pathDir = $value;
        return $this;
    }

    /**
     * 设置分割日期文件夹格式
     * @param string $value
     * @return $this
     */
    public function splitDate($value) {
        $this->splitDate = $value;
        return $this;
    }

    /**
     * 是否上传到临时文件夹
     * @param boolean $value
     * @return $this
     */
    public function inTemp($value) {
        $this->inTemp = $value;
        return $this;
    }

    /**
     * 添加文件类型判断
     * @param array $types
     */
    public function addType($types) {
        array_extend($this->types, $types);
        return $this;
    }

    /**
     * 效验文件是否正确，自动过滤掉非法文件
     * @return string
     */
    public function isValid() {
        $rs = false;
        foreach ($this->files as $i => $file) {
            if ($file['error'] != 0) {
                throw new FileUploadException('File [' . $file['name'] . '] not complete');
            } elseif ($file['size'] / 1000 > $this->maxSize) {
                throw new FileUploadException('File [' . $file['name'] . '] size cannot be more than [' . $this->maxSize . '] KB');
            } elseif (!in_array($this->type($file['tmp_name']), $this->acceptTypes)) {
                throw new FileUploadException('File [' . $file['name'] . '] type cannot be except [' . '.' . implode('.', $this->acceptTypes) . ']');
            } else {
                $rs = true;
                continue;
            }
            if (file_exists($file['tmp_name'])) {
                unlink($file['tmp_name']);
            }
            unset($this->files[$i]);
            $this->errNum++;
        }
        return $rs;
    }

    /**
     * 效验后产生错误的文件数
     * @return int
     */
    public function errNum() {
        return $this->errNum;
    }

    /**
     * 保存文件
     * 临时文件夹固定在static/temp目录中
     * @return array
     */
    public function save() {
        // 生成目录
        $dir = $this->inTemp ? 'temp/' . date('Ymd') : trim($this->pathDir, '/') . (empty($this->splitDate) ? '' : '/' . date($this->splitDate));
        $pathname = v\App::absolutePath("static/$dir", '/');
        is_dir($pathname) or mkdir($pathname, 0777, true);

        $files = [];
        foreach ($this->files as $file) {
            $type = $this->type($file['tmp_name']);
            $name = $this->prefix . (isset($file['id']) ? $file['id'] : uniqid());
            $fileUrl = "{$dir}/{$name}.{$type}";
            $filename = storage_path("upload/$fileUrl");
            // 保存文件不能使用move_uploaded_file,因为PUT的文件is_uploaded_file不能通过
            if (strtolower(trim(dirname($file['tmp_name']), '/')) === trim(strtolower(sys_get_temp_dir()), '/')) {
                if (rename($file['tmp_name'], $filename)) {
                    $files[] = $fileUrl;
                } else {
                    throw new FileUploadException('File [' . $file['name'] . '] save failure');
                }
            }
        }
        return $files;
    }

    /**
     * 删除文件,支持多个
     * @param string $path img在项目中的地址，是url地址
     */
    public function delete($path) {
        $files = arrayval($path);
        foreach ($files as $file) {
            if (!empty($file) && strpos(ltrim($file, '/'), ltrim($this->pathDir, '/')) === 0) {  // 检查是否上传文件夹的文件
                $file = storage_path("/upload/$file");
                if (file_exists($file))
                    unlink($file);
            }
        }
    }

    /**
     * 移动零时文件到新文件架
     * @param string|array $path 文件URL
     * @return array|string 移动后的新文件地址  根据path的类型返回值
     */
    public function move2dir($path) {
        $news = [];
        $files = arrayval($path);
        foreach ($files as $file) {
            if (!empty($file) && strpos(ltrim($file, '/'), 'temp/') === 0) {  // 检查是否在临时文件夹中
                $filename = storage_path("/upload/$file");
                if (file_exists($filename)) {
                    $fileNew = (trim($this->pathDir, '/') . (empty($this->splitDate) ? '' : '/' . date($this->splitDate))) . '/' . basename($filename);
                    $news[] = $fileNew;
                    $fileNew = storage_path("/upload/$fileNew");
                    $pathname = dirname($fileNew); // 先建立目录
                    is_dir($pathname) or mkdir($pathname, 0777, true);
                    if (!rename($filename, $fileNew)) {
                        throw new FileUploadException('File [' . $file['name'] . '] save failure');
                    }
                }
            }
        }
        return is_string($path) ? implode(',', $news) : $news;
    }

    /**
     * 获得文件类型
     * @param string $name
     * @return string
     */
    public function type($filename) {
        $file = fopen($filename, "rb");
        $bin = fread($file, 2); //只读2字节
        fclose($file);
        $strInfo = unpack('c2chars', $bin);
        $typeCode = $strInfo['chars1'] . $strInfo['chars2'];
        $fileType = isset($this->types[$typeCode]) ? $this->types[$typeCode] : substr(strrchr($filename, '.'), 1);
        return $fileType;
    }

}