<?php
/**
 * Created by PhpStorm.
 * User: mercuriuz
 * Date: 17.1.23
 * Time: 8:18
 */

class WAPI
{
    private $login,
            $pass;

    const WAPI_URL = 'https://api.wedos.com/wapi/json';

    function __construct($login, $pass)
    {
        $this->login = $login;
        $this->pass = $pass;
    }

    private function buildQuery($command, $data = null)
    {
        $query = array();
        $query['request'] = array();
        $query['request']['user'] = $this->login;
        $query['request']['auth'] = sha1($this->login.sha1($this->pass).date('H', time()));
        $query['request']['command'] = $command;
        if(isset($data))
            $query['request']['data'] = $data;

        return json_encode($query);
    }

    private function query($query)
    {
        $ch = curl_init(self::WAPI_URL);
        curl_setopt($ch,CURLOPT_TIMEOUT,60);
        curl_setopt($ch,CURLOPT_POST, true);
        curl_setopt($ch,CURLOPT_POSTFIELDS, 'request=' . $query);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $res = curl_exec($ch);
        curl_close($ch);

        return json_decode($res, true);
    }

    private function processResult($result, $query)
    {
        if(!isset($result['response']) OR !isset($result['response']['code']))
            throw new WAPI_Exception('FATAL ERROR', 0, $query);

        if($result['response']['code'] == 1000 AND !isset($result['response']['data'])) //everything ok, no response data
            return true;
        elseif($result['response']['code'] == 1000) //everything ok, return response data
            return $result['response'];
        else //something went wrong, return error code
            $this->returnError($result['response']['code'], $query);
    }

    function ping()
    {
        $query = $this->buildQuery('ping');
        $result = $this->query($query);
        return $this->processResult($result, $query);
    }

    function domainsList()
    {
        $query = $this->buildQuery('domains-list');
        $result = $this->query($query);
        return $this->processResult($result, $query);
    }

    function domainInfo($domain)
    {
        $query = $this->buildQuery('domain-info', array('name' => $domain));
        $result = $this->query($query);
        return $this->processResult($result, $query);
    }

    function dnsRowsList($domain)
    {
        $query = $this->buildQuery('dns-rows-list', array('domain' => $domain));
        $result = $this->query($query);
        return $this->processResult($result, $query);
    }

    function dnsRowAdd($domain, $name, $ttl, $type, $data, $dbRowId = null)
    {
        $array = array('domain' => $domain, 'name' => $name, 'ttl' => $ttl, 'type' => $type, 'rdata' => $data);
        if($dbRowId > 0)
            $array['auth_comment'] = 'DBROW '.$dbRowId;

        $query = $this->buildQuery('dns-row-add', $array);
        $result = $this->query($query);
        return $this->processResult($result, $query);
    }

    function dnsRowUpdate($domain, $rowID, $ttl, $data)
    {
        $array = array('domain' => $domain, 'row_id' => $rowID, 'ttl' => $ttl, 'rdata' => $data);
        $query = $this->buildQuery('dns-row-update', $array);
        $result = $this->query($query);
        return $this->processResult($result, $query);
    }

    function dnsRowDelete($domain, $rowID)
    {
        $query = $this->buildQuery('dns-row-delete', array('domain' => $domain, 'row_id' => $rowID));
        $result = $this->query($query);
        return $this->processResult($result, $query);
    }

    function domainCommit($domain)
    {
        $query = $this->buildQuery('dns-domain-commit', array('name' => $domain));
        $result = $this->query($query);
        return $this->processResult($result, $query);
    }

    private function returnError($errId, $query)
    {
        $errors = array(1000 => 'OK',
                        1001 => 'Request pending',
                        1002 => 'Notification aquired',
                        1003 => 'Empty notifications queue',
                        1200 => 'Domain transfered out',
                        2000 => 'Request parse error',
                        2001 => 'Invalid request – required parameter missing: user',
                        2002 => 'Invalid request – required parameter missing: auth',
                        2003 => 'Invalid request – required parameter missing: command',
                        2004 => 'Invalid request – only one data element is allowed',
                        2005 => 'Invalid request – clTRID parameter is too long',
                        2006 => 'Requests limit exceeded',
                        2007 => 'Invalid request – maximum request size exceeded',
                        2008 => 'Invalid request – request is too complex',
                        2009 => 'Invalid request – request is empty',
                        2010 => 'Unknown command',
                        2011 => 'Command disabled',
                        2050 => 'Authentication failure',
                        2051 => 'Access not allowed from this IP address',
                        2052 => 'IP adress temporarily blocked due to too many failed requests',
                        2100 => 'Required parameter missing',
                        2101 => 'Parameters mismatch',
                        2102 => 'Invalid request – input data encoding mismatch',
                        2150 => 'Notification polling not allowed for this account',
                        2151 => 'Notification does not exist',
                        2201 => 'Unsupported TLD',
                        2202 => 'Invalid or unsupported domain name format',
                        2203 => 'Invalid period',
                        2204 => 'Invalid request – required parameter missing: owner_c',
                        2205 => 'Invalid request – required parameter missing: domain',
                        2206 => 'Invalid request – internal error',
                        2207 => 'Invalid request – invalid format: owner_c',
                        2208 => 'Invalid request – invalid format: admin_c',
                        2209 => 'Invalid request – invalid format: nsset',
                        2210 => 'Invalid request – invalid format: dns',
                        2211 => 'Invalid request – too many DNS servers',
                        2212 => 'Invalid request – required parameter missing: nsset',
                        2213 => 'Invalid request – required parameter missing: dns',
                        2214 => 'Invalid request – duplicate DNS entry',
                        2215 => 'Invalid request – required parameter missing: auth info',
                        2216 => 'Invalid or unsupported contact name format',
                        2217 => 'Invalid request – invalid contact data',
                        2218 => 'Invalid request – contact identificator is created automatically',
                        2219 => 'Invalid request – required parameter missing: company',
                        2220 => 'Invalid request – required parameter missing: fname',
                        2221 => 'Invalid request – required parameter missing: lname',
                        2222 => 'Invalid request – required parameter missing: email',
                        2223 => 'Invalid request – invalid format: email',
                        2224 => 'Invalid request – required parameter missing: email2',
                        2225 => 'Invalid request – invalid format: email2',
                        2226 => 'Invalid request – required parameter missing: phone',
                        2227 => 'Invalid request – invalid format: phone',
                        2228 => 'Invalid request – required parameter missing: fax',
                        2229 => 'Invalid request – invalid format: fax',
                        2230 => 'Invalid request – required parameter missing: ic',
                        2231 => 'Invalid request – invalid format: ic',
                        2232 => 'Invalid request – required parameter missing: dic',
                        2233 => 'Invalid request – invalid format: dic',
                        2234 => 'Invalid request – required parameter missing: addr_street',
                        2235 => 'Invalid request – required parameter missing: addr_city',
                        2236 => 'Invalid request – required parameter missing: addr_zip',
                        2237 => 'Invalid request – required parameter missing: addr_country',
                        2238 => 'Invalid request – invalid format: addr_country',
                        2239 => 'Invalid request – required parameter missing: addr_state',
                        2240 => 'Invalid request – required parameter missing in other data',
                        2241 => 'Invalid request – invalid format in other data',
                        2242 => 'Invalid request – invalid status',
                        2243 => 'Invalid request – function SendAuthInfo is not allowed for this domain',
                        2244 => 'Invalid request – contact transfer is not allowed for this TLD',
                        2245 => 'Invalid request – rules agree missing',
                        2246 => 'Invalid request – invalid format: rules agree',
                        2247 => 'Invalid or unsupported NSSET name format',
                        2248 => 'Invalid request – invalid NSSET data',
                        2249 => 'Invalid request – NSSET transfer is not allowed for this TLD',
                        2250 => 'Invalid request – domain check – max. query hour limit exceeded',
                        2251 => 'Invalid request – domain check failed, try again later',
                        2252 => 'Invalid request – domain transfer check – max. query hour limit exceeded',
                        2253 => 'Invalid request – invalid format: addr_zip',
                        2301 => 'Invalid request – WDNS domain – invalid type format',
                        2302 => 'Invalid request – WDNS domain – required parameter missing: primary_ip',
                        2303 => 'Invalid request – WDNS domain – invalid primary_ip format',
                        2304 => 'Invalid request – WDNS domain – invalid axfr_enabled format',
                        2305 => 'Invalid request – WDNS domain – required parameter missing: axfr_ips',
                        2306 => 'Invalid request – WDNS domain – invalid axfr_ips format',
                        2308 => 'Invalid request – WDNS domain – invalid ns format',
                        2309 => 'Invalid request – WDNS domain – invalid rdtype format',
                        2310 => 'Invalid request – WDNS domain – rows count limit reached',
                        2311 => 'Invalid request – WDNS domain – invalid name format',
                        2312 => 'Invalid request – WDNS domain – invalid name format for this rdtype',
                        2313 => 'Invalid request – WDNS domain – invalid CNAME for this name',
                        2314 => 'Invalid request – WDNS domain – invalid rdata format for this rdtype',
                        2315 => 'Invalid request – WDNS domain – invalid TTL',
                        2316 => 'Invalid request – WDNS domain – this row exists',
                        2317 => 'Invalid request – WDNS domain – invalid TTL limit',
                        2318 => 'Invalid request – WDNS domain – unsupported action for secondary domain',
                        2319 => 'Invalid request – WDNS domain – unsupported action for primary domain',
                        2320 => 'Invalid request – WDNS domain – invalid or unsupported new domain name format',
                        2321 => 'Invalid request – WDNS domain – unsupported TLD of new domain name',
                        2322 => 'Invalid request – WDNS domain – maximum user domains count exceeded',
                        3001 => 'Billing error – invalid currency',
                        3002 => 'Billing error – insufficient credit',
                        3003 => 'Account error – undefined contact data',
                        3201 => 'Domain is registered',
                        3202 => 'Domain is not available (unknown reason)',
                        3203 => 'Domain is not available',
                        3204 => 'Domain is not available, quarantine',
                        3205 => 'Domain is not available, reserved',
                        3206 => 'Domain is not available, blocked',
                        3207 => 'registered by target registrar',
                        3208 => 'Transfer not allowed, expiration too close',
                        3209 => 'Domain send auth info failed',
                        3210 => 'Domain send auth info failed: domain is not available',
                        3211 => 'Contact owner_c is not available‘',
                        3212 => 'Contact admin_c is not available',
                        3213 => 'Contact send auth info failed: contact is not available',
                        3214 => 'NSSET is not available',
                        3215 => 'Contact send auth info failed',
                        3216 => 'Domain info failed',
                        3217 => 'NSSET send auth info failed',
                        3219 => 'Domain already pending for transfer',
                        3220 => 'Domain is already registered in our system',
                        3221 => 'Domain create failed',
                        3222 => 'Domain open failed',
                        3223 => 'Domain authentication error',
                        3224 => 'Domain renew failed',
                        3225 => 'Domain update DNS failed',
                        3226 => 'Domain transfer failed',
                        3227 => 'Domain transfer failed – authorization error',
                        3228 => 'Contact is not supported',
                        3229 => 'Contact is not available',
                        3230 => 'Contact create failed',
                        3231 => 'Contact is already registered',
                        3232 => 'Contact is not available (unknown reason)',
                        3233 => 'Contact is not available',
                        3234 => 'Contact is not available, quarantine',
                        3235 => 'Contact is not available, reserved',
                        3236 => 'Contact is not available, blocked',
                        3237 => 'Contact is already registered by target registrar',
                        3238 => 'Contact update – authorization error',
                        3239 => 'Contact update failed',
                        3240 => 'Contact transfer – authorization error',
                        3241 => 'Contact transfer failed',
                        3242 => 'NSSET is not supported',
                        3243 => 'NSSET is not available',
                        3244 => 'NSSET create failed',
                        3245 => 'NSSET is registered',
                        3246 => 'NSSET is not available (unknown reason)',
                        3247 => 'NSSET is not available',
                        3248 => 'NSSET is not available, quarantine',
                        3249 => 'NSSET is not available, reserved',
                        3250 => 'NSSET is not available, blocked',
                        3251 => 'NSSET is registered by target registrar',
                        3252 => 'NSSET update – authorization error',
                        3254 => 'NSSET update failed',
                        3255 => 'NSSET transfer – authorization error',
                        3256 => 'NSSET transfer failed',
                        3257 => 'NSSET send auth info failed: NSSET is not available',
                        3301 => 'WDNS domain delete failed',
                        3302 => 'WDNS domain add failed',
                        3303 => 'WDNS domain exists',
                        3304 => 'WDNS domain update failed',
                        3305 => 'WDNS domain user locked',
                        3306 => 'WDNS domain is deleted',
                        3307 => 'WDNS domain row add failed',
                        3308 => 'WDNS domain row delete failed',
                        3309 => 'WDNS domain row ID does not exists',
                        3310 => 'WDNS domain row update failed',
                        3311 => 'WDNS domain copy failed',
                        3312 => 'WDNS new domain exists',
                        4000 => 'Internal error',
                        4001 => 'Internal exception',
                        4002 => 'Billing error – credit deduction failed',
                        4003 => 'Billing error – billing failed',
                        4201 => 'Domain check failed, try again later',
                        4202 => 'Contact owner_c is not available, try again later',
                        4203 => 'Contact admin_c is not available, try again later',
                        4204 => 'NSSET is not available, try again later',
                        4205 => 'Domain info failed, try again later',
                        4206 => 'Domain transfer check failed, try again later',
                        4207 => 'Domain create failed, try again later',
                        4208 => 'Domain renew failed, try again later',
                        4209 => 'Domain transfer failed, try again later',
                        4210 => 'Domain update DNS failed, try again later',
                        4211 => 'Contact check failed, try again later',
                        4212 => 'Contact is not available, try again later',
                        4213 => 'Contact create failed, try again later',
                        4214 => 'Domain send auth info failed, try again later',
                        4215 => 'Contact update failed, try again later',
                        4216 => 'Contact transfer failed, try again later',
                        4217 => 'Contact send auth info failed, try again later',
                        4218 => 'NSSET check failed, try again later',
                        4219 => 'NSSET create failed, try again later',
                        4220 => 'NSSET update failed, try again later',
                        4221 => 'NSSET transfer failed, try again later',
                        4222 => 'NSSET send auth info failed, try again later',
                        5000 => 'Fatal error',
                        5001 => 'Internal authentication error',
                        5002 => 'Internal pricing error',
                        5003 => 'Out of order');

        throw new WAPI_Exception($errors[$errId], $errId, $query);
    }
}

class WAPI_Exception extends Exception
{
    private $query;

    function __construct($message, $code, $query)
    {
        parent::__construct($message, $code);
        $this->query = $query;
    }

    public function getQuery()
    {
        return $this->query;
    }
}