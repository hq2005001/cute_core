<?php

namespace Cute;

use Cute\exceptions\ApiException;

class RestController extends Controller
{

    protected $model = '';

    protected $allowFields = [];

    protected $forbidFields = [];

    /**
     * 视图路径
     * @var string
     */
    protected $viewPath = '';

    /**
     * 模块标题
     * @var string
     */
    protected $title = '';

    /**
     * 当前模块 用于更新目录状态
     * @var string
     */
    protected $module = '';

    /**
     * 中间件
     * @var array
     */
    protected $middlewares = [
        'Staff'
    ];


    /**
     * 允许查询的字段
     * @var array
     */
    protected $queryFields = [];

    protected function filterQuery($params)
    {
        $query = $params;
        if(!empty($this->queryFields)) {
            array_column_filter($query, $this->queryFields);
        }
        array_filter_null($query);
        $query = app()->model($this->model)->castData($query);
        foreach ($query as &$v) {  // 查找时数组的处理
            if (is_array($v))
                $v = reset($v);
        }
        // 允许多个ID查询
        if (!empty($params['ids'])) {
            $query['id'] = ['in', arrayval($params['ids'])];
        }
        return $query;
    }

    /**
     * 处理 filed limit skip sort
     * @param array $params
     * @return array
     */
    protected function filterOptions($params) {
        // field limit skip sort
        $options = array_filter_key($params, ['field', 'skip', 'limit', 'sort']);
        // 分页
        $options['row'] = empty($params['row']) ? null : intval($params['row']);
        $options['page'] = empty($params['page']) ? 1 : intval($params['page']);
        // 排序
        if (!empty($options['sort']) && !empty($this->sortFields)) {
            if (is_string($options['sort'])) {
                if (!in_array($options['sort'], $this->sortFields))
                    unset($options['sort']);
            } else {
                array_column_filter($options['sort'], $this->sortFields);
            }
        }
        return $options;
    }

    /**
     * 上传文件
     * 只要有一组文件上传成功，即视为成功
     * @param array $item 原有字段
     * @return boolean
     */
    protected function upFile($item = []) {
        $model = app()->model($this->model);
        $upData = $model->data();
        $fields = $model->fields();
        // 取得允许上传文件类型与字段
        foreach ($fields as $field => $options) {
            $type = $options[0];
            if (!isset($upData[$field]) || !isset($options[1]) || ($type !== 'file' && $type !== 'files')) {
                unset($fields[$field]);
            } else {
                $fields[$field] = $type;
            }
        }
        if (empty($fields))
            return 1;

        $rs = empty($_FILES) ? 1 : 0;
        $upFile = app('upload')->options($this->config('upfile', []));
        foreach ($fields as $field => $type) {
            // 删除文件处理
            $files = $upData[$field];
            if (isset($item[$field])) {
                if ($type === 'file') {
                    if ($fields !== $item[$field])
                        $upFile->delete($item[$field]);
                } elseif (!empty($item[$field])) {
                    $files1 = array_diff($item[$field], $files);  // 找出被删除的数据
                    if (!empty($files1))
                        $upFile->delete($files1);
                }
            }
            // 检查保存上传的文件
            $upName = "upfile_$field";
            if (!empty($_FILES[$upName]) && $upFile->file($_FILES[$upName])->isValid()) {
                $urls = $upFile->save();
                if (!empty($urls)) {
                    if ($type === 'file') {
                        // 只允许单个文件则覆盖
                        $files = reset($urls);
                    } else {
                        // 多个文件，按顺序覆盖blob:开头的上传文件
                        foreach ($files as $k => $file) {
                            if (strpos($file, 'blob:') === 0) {
                                $files[$k] = array_shift($urls);
                            }
                        }
                    }
                    $rs++;
                }
            }
            // 删除预览的客户本地图片
            if (is_array($files)) {
                foreach ($files as $k => $file) {
                    if (strpos($file, 'blob:') === 0) {
                        unset($files[$k]);
                    }
                }
            }
            $model->addData([$field => $files]);
        }
        return $rs;
    }

    /**
     * 添加效验成功后调用该函数
     */
    protected function hookPostIsValid() {

    }

    /**
     * 修改效验成功后调用该函数
     * @param array $item 原数据
     */
    protected function hookPutIsValid($item) {

    }

    protected function hookInsertData(&$params) {

    }

    public function getById()
    {
        $params = app('req')->input();
        if (empty($params['id'])) {
            throw new ApiException('ID 不能为空');
        }
        // field字段
        $options = array_filter_key($params, ['field']);
        $data = app()->model($this->model)->getByID($params['id'], $options);

        // 错误返回
        if (empty($data)) {
            throw new ApiException('数据不存在');
        }

        // 去除不允许返回的信息
        if (!empty($this->forbidFields)) {
            array_column_unset($data, $this->forbidFields);
        }

        return $this->apiData($data);
    }

    protected function afterGet(&$data)
    {

    }

    public function get()
    {
        $type = app('req')->type();
        if($type == 'json') {
            $params = app('req')->input();
            // ID查询
            if (!empty($params['id'])) {
                return $this->getById();
            }
            $options = $this->filterOptions($params);
            $query = $this->filterQuery($params);
            $model = app()->model($this->model);
            $data = $model->where($query)->getPaging($options);

            $this->afterGet($data);

            // 去除不允许返回的信息
            if (!empty($this->forbidFields)) {
                array_column_unset($data['data'], $this->forbidFields);
            }
            // 数据返回，API接口数据移动要return返回
            return $this->apiData($data);
        } else {
            return view($this->viewPath, [
                'title' => $this->title,
                'module' => $this->module
            ]);
        }

    }

    public function post()
    {
        $params = app('req')->input();
        $model = app()->model($this->model);
        $this->hookInsertData($params);
        $model->setData($params);
        if ($model->isMust()) {  // 注意添加需要必填校验
            $this->hookPostIsValid();
            // 文件上传处理，文件上传不成功不会保存
            if ($this->upFile() && $model->addOne()) {
                // 取得成功返回的数据
                $options = array_filter_key($params, ['field']);
                $lastID = $model->lastID();
                $data = $model->getByID($lastID, $options);
                return $this->apiData($data);
            }
        }
        // 数据返回，API接口数据移动要return返回
        throw new ApiException('添加失败');
    }

    public function put()
    {
        $params = app('req')->input();
        if(empty($params['id'])) {
            throw new ApiException('Id必传');
        }
        //检查数据是否存在
        $model = app()->model($this->model);
        $data = $model->getById($params['id']);
        if(empty($data)) {
            throw new ApiException('数据未找到');
        }
        //只允许修改许可的字段
        if(!empty($this->allowFields)) {
            array_column_filter($params, $this->allowFields);
        }
        $model->setData($params)->subData($data); //去掉值相同的值
        if($model->isValid())
        {
            $this->hookPutIsValid($data);
            $rs1 = !$model->hasData() ? 1 : $this->upFile($data);  // 有字段变化进行文件上传处理
            $rs = $model->upByID($data['id']);
            if ($rs1 && ($rs || !$model->hasData())) {  // 如果没有修改也返回成功，文件上传出现错误则返回错误
                // 取得成功返回的数据
                $options = array_filter_key($params, ['field']);
                $data = $model->getByID($data['id'], $options);
                return $this->apiData($data);
            }
        }
        throw new ApiException('修改失败');
    }

    protected function hookDeleteIds(&$id)
    {

    }

    public function delete()
    {
        $id = app('req')->input('id');
        $this->hookDeleteIds($id);
        //查找数据是否存在

        $model = app()->model($this->model);
        $rs = $model->delByIds($id);
        if($rs) {
            return $this->apiData(['id' => $id]);
        }
        throw new ApiException('删除失败');
    }

}
