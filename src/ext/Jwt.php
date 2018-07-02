<?php

namespace Cute\ext;

class Jwt
{
    /**
     * 过期时间 秒
     * @var integer 
     */
    protected $expire_time = 24 * 3600; //默认一天不过期

    protected $secret = '123Ijsdf)*'; //加盐字符串

    protected $headers = [
        'typ' => 'JWT',
        'alg' => 'SHA256'
    ];
    
    protected $alg = 'SHA256'; //加密算法


    protected $payloads = [];

    protected $headerStr='';
    protected $payloadStr = '';
    protected $signatureStr = '';

    /**
     * 设置jwt头
     * @param type $headers
     * @return $this
     */
    public function setHeader($headers)
    {
        if(!empty($headers)) {
            $this->headers = $headers;
        }
        $this->alg = $this->headers['alg'];
        $this->headerStr = base64_encode(json_encode($this->headers));
        return $this;
    }

    protected function setPayloads($payLoads)
    {
        $this->payloads = [
            'iss' => '',
            'iat' => time(),
            'exp' => time() + $this->expire_time,
        ];
        if(!empty($payLoads)) {
            $this->payloads = array_merge($this->payloads, array_diff_key($payLoads, [
                'iss' => -1, 'iat' => -1, 'exp' => -1
            ]));
        }
        $this->payloadStr = base64_encode(json_encode($this->payloads));
        return $this;        
    }
    
    protected function signature()
    {
        $this->signatureStr = hash_hmac($this->alg, $this->headerStr.'.'.$this->payloadStr, $this->secret);
    }

    

    /**
     * 检验token是否合法
     * @param type $token
     * @return boolean|array
     */
    public function check($token)
    {
        list($headerStr, $payloadStr, $signatureStr) = explode('.', $token);
        //校验signature
        $header = json_decode(base64_decode($headerStr), true);
        $payload = json_decode(base64_decode($payloadStr), true);
        
        $correctSignature = hash_hmac($header['alg'], $headerStr.'.'.$payloadStr, $this->secret);
        
        if($correctSignature != $signatureStr) {
            return false;
        }
        
        //校验是否过期
        if(!empty($payload['iat']) && $payload['iat']> time()) {
            return false;
        }
        
        if(!empty($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        return $payload;
    }
    
    public function generate()
    {
        if(empty($this->headerStr)) {
            $this->setHeader([]);
        }
        if(empty($this->payloadStr)) {
            $this->setPayloads([]);
        }
        $this->signature();
        return implode('.', [
            $this->headerStr,
            $this->payloadStr,
            $this->signatureStr
        ]);
    }
}
