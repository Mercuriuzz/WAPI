<?php
/**
 * Created by PhpStorm.
 * User: mercuriuz
 * Date: 17.1.23
 * Time: 12:11
 */

class WapiMysql
{
    /* @var $db Database */
    private $db;

    function __construct($db)
    {
        $this->db = $db;
    }

    function updateDNSonWedos($accountsIds = array(), $domains = array())
    {
        if(!is_array($accountsIds) OR !is_array($domains))
        {
            echo "Error: Function updateDNSonWedos parameters must be arrays";
            return;
        }

        echo "\n";
        echo "Updating Wedos DNS...\n";
        echo "\n";

        $accounts = $this->getAccounts($accountsIds);

        foreach($accounts as $account)
        {
            $login = $account['login'];
            $pass = $account['pass'];
            echo "   ".'Processing account '.$login."\n";

            $wapi = new WAPI($login, $pass);

            try
            {
                $apiDomains = $wapi->domainsList();
            }
            catch (WAPI_Exception $e)
            {
                echo "\n";
                echo "Error no. ".$e->getCode()."\n";
                echo "Error: ".$e->getMessage()."\n";
                echo "Query: ".$e->getQuery()."\n";
                echo "\n";
                echo "\n";
                die();
            }

            $domainsList = $apiDomains['data']['domain'];

            foreach($domainsList as $domain)
            {
                if(count($domains) AND in_array($domain['name'], $domains))
                    $this->updateDomainDnsOnWedos($account, $wapi, $domain);
                elseif(count($domains) == 0) // update everything
                    $this->updateDomainDnsOnWedos($account, $wapi, $domain);
            }
        }
    }

    /**
     * @param $account
     * @param $wapi WAPI
     * @param $domain
     */
    private function updateDomainDnsOnWedos($account, $wapi, $domain)
    {
        $resDomain = $this->db->buildSql()->table('domains')->where(array('name' => $domain['name'], 'accounts_id' => $account['id']))->execute();

        echo "\n\t".'Updating DNS on domain '.$domain['name']."...\n";

        if($resDomain->count())
        {
            $dnsRecords = $this->db->buildSql()->table('dns')->where(array('domains_id' => $resDomain['id'], 'status' => array('UPDATE', 'CREATE', 'DELETE')))->execute();

            if($dnsRecords->count())
            {
                foreach($dnsRecords as $dnsRecord)
                {
                    switch($dnsRecord['status'])
                    {
                        case 'UPDATE':
                        {
                            echo "\t   > ".'Calling update of '.$dnsRecord['type']." type record at ".($dnsRecord['name'] == "" ? $domain['name'] : $dnsRecord['name'].$domain['name'])." with value \"".$dnsRecord['data']."\"...\n";
                            $response = $wapi->dnsRowUpdate($domain['name'], $dnsRecord['row_id'], $dnsRecord['ttl'], $dnsRecord['data']);

                            if($response == true)
                            {
                                echo "\t   > Done. \n";
                                $this->db->buildSql()->table('dns')->update(array('status' => 'ACTIVE'))->where(array('id' => $dnsRecord['id']))->execute();
                            }
                            else
                                echo "\t   > Failed! \n";
                        }
                        break;
                        case 'CREATE':
                        {
                            echo "\t   > ".'Calling create of '.$dnsRecord['type']." type record at ".($dnsRecord['name'] == "" ? $domain['name'] : $dnsRecord['name'].$domain['name'])." with value \"".$dnsRecord['data']."\"...\n";
                            $response = $wapi->dnsRowAdd($domain['name'], $dnsRecord['name'], $dnsRecord['ttl'], $dnsRecord['type'], $dnsRecord['data'], $dnsRecord['id']);
                            if($response == true)
                                echo "\t   > Done. \n";
                            else
                                echo "\t   > Failed! \n";

                        }
                        break;
                        case 'DELETE':
                        {
                            echo "\t   > ".'Calling deletion of '.$dnsRecord['type']." type record at ".($dnsRecord['name'] == "" ? $domain['name'] : $dnsRecord['name'].'.'.$domain['name'])." with value \"".$dnsRecord['data']."\"...\n";
                            $response = $wapi->dnsRowDelete($domain['name'], $dnsRecord['row_id']);

                            if($response == true)
                            {
                                echo "\t   > Done. \n";
                                $this->db->buildSql()->table('dns')->update(array('status' => 'DELETED'))->where(array('id' => $dnsRecord['id']))->execute();
                            }
                            else
                                echo "\t   > Failed! \n";
                        }
                    }
                }

                $wapi->domainCommit($domain['name']);
            }
            else
            {
                echo "\t   > ".'Nothing to do'."\n";
            }
        }
    }

    /**
     * @param array $accountsIds
     * @param array $domains
     */
    function updateDomainsInDb($accountsIds = array(), $domains = array())
    {
        if(!is_array($accountsIds) OR !is_array($domains))
        {
            echo "Error: Function updateDomainsInDb parameters must be arrays";
            return;
        }

        echo "\n";
        echo "Updating domains in DB...\n";
        echo "\n";

        $accounts = $this->getAccounts($accountsIds);

        foreach($accounts as $account)
        {
            $login = $account['login'];
            $pass = $account['pass'];
            echo "   ".'Processing account '.$login."\n";

            $wapi = new WAPI($login, $pass);

            try
            {
                $apiDomains = $wapi->domainsList();
            }
            catch (WAPI_Exception $e)
            {
                echo "\n";
                echo "Error no. ".$e->getCode()."\n";
                echo "Error: ".$e->getMessage()."\n";
                echo "Query: ".$e->getQuery()."\n";
                echo "\n";
                echo "\n";
                die();
            }

            $domainsList = $apiDomains['data']['domain'];

            foreach($domainsList as $domain)
            {
                if(count($domains) AND in_array($domain['name'], $domains))
                    $this->updateDomainInDb($account, $wapi, $domain);
                elseif(count($domains) == 0) // update everything
                    $this->updateDomainInDb($account, $wapi, $domain);
            }
        }
    }

    /**
     * @param $account
     * @param $wapi WAPI
     * @param $domain
     */
    private function updateDomainInDb($account, $wapi, $domain)
    {
        echo "\t".'Updating domain '.$domain['name']." in DB...\n";
        $res = $this->db->buildSql()->table('domains')->where(array('name' => $domain['name']))->execute();

        if($res->count())
        {
            $domainId = $res['id'];
            $this->db->buildSql()->table('domains')->update($domain)->where(array('id' => $domainId))->execute();
        }
        else
        {
            $domain['accounts_id'] = $account['id'];
            $this->db->buildSql()->table('domains')->insert($domain)->execute();
            $domainId = $this->db->getInsertId();
        }

        if(in_array($domain['status'], array('active', 'renew_processing')))
        {
            $this->updateDomainDataInDb($wapi, $domainId, $domain);
        }
        echo "\t   > ".'Update of domain '.$domain['name'].' in DB completed.'."\n\n";
    }

    /**
     * @param $wapi WAPI
     * @param $domainId
     * @param $domain
     */
    private function updateDomainDataInDb($wapi, $domainId, $domain)
    {
        echo "\t   > ".'Updating info on domain '.$domain['name']." in DB...\n";

        try
        {
            $domainInfo = $wapi->domainInfo($domain['name']);
        }
        catch (WAPI_Exception $e)
        {
            echo "\n";
            echo "Error no. ".$e->getCode()."\n";
            echo "Error: ".$e->getMessage()."\n";
            echo "Query: ".$e->getQuery()."\n";
            echo "\n";
            echo "\n";
            die();
        }

        $info = array('domains_id' => $domainId,
                      'nsset' => $domainInfo['data']['domain']['nsset'],
                      'owner' => $domainInfo['data']['domain']['owner_c'],
                      'admin' => $domainInfo['data']['domain']['admin_c'],
                      'create_date' => $domainInfo['data']['domain']['setup_date']);

        $this->db->buildSql()->table('domains_data')->replaceInto($info)->execute();

        if($domainInfo['data']['domain']['nsset'] == 'WEDOS')
        {
            $this->updateDnsInDb($wapi, $domainId, $domain);
        }
    }

    /**
     * @param $wapi WAPI
     * @param $domainId
     * @param $domain
     */
    private function updateDnsInDb($wapi, $domainId, $domain)
    {
        echo "\t   > ".'Updating DNS on domain '.$domain['name']." in DB...\n";

        try
        {
            $dns = $wapi->dnsRowsList($domain['name']);
        }
        catch (WAPI_Exception $e)
        {
            echo "\n";
            echo "Error no. ".$e->getCode()."\n";
            echo "Error: ".$e->getMessage()."\n";
            echo "Query: ".$e->getQuery()."\n";
            echo "\n";
            echo "\n";
            die();
        }

        $dnsEntries = $dns['data']['row'];

        $validEntries = array();

        foreach($dnsEntries as $dnsEntry)
        {
            $entryId = $this->updateDnsRowInDb($domainId, $dnsEntry);

            $validEntries[] = $entryId;

            $this->db->buildSql()->table('dns')->update(array('status' => 'DELETED', 'changed_date' => SqlBuilder::noEscape('NOW()')))->where(array('domains_id' => $domainId))->whereNot(array('id' => $validEntries))->execute();
        }
    }

    private function updateDnsRowInDb($domainId, $dnsEntry)
    {
        if(preg_match('/DBROW (\d+)/', $dnsEntry['author_comment'], $matches))
            $res = $this->db->buildSql()->table('dns')->where(array('domains_id' => $domainId, 'id' => $matches[1]))->execute();
        else
            $res = $this->db->buildSql()->table('dns')->where(array('domains_id' => $domainId, 'row_id' => $dnsEntry['ID']))->execute();

        if($res->count())
        {
            $entryId = $res['id'];
            $entry = array('row_id' => $dnsEntry['ID'],
                           'name' => $dnsEntry['name'],
                           'ttl' => $dnsEntry['ttl'],
                           'type' => $dnsEntry['rdtype'],
                           'data' => $dnsEntry['rdata'],
                           'changed_date' => $dnsEntry['changed_date'],
                           'status' => 'ACTIVE');

            $this->db->buildSql()->table('dns')->update($entry)->where(array('id' => $entryId))->execute();
        }
        else
        {
            $entry = array('domains_id' => $domainId,
                           'row_id' => $dnsEntry['ID'],
                           'name' => $dnsEntry['name'],
                           'ttl' => $dnsEntry['ttl'],
                           'type' => $dnsEntry['rdtype'],
                           'data' => $dnsEntry['rdata'],
                           'changed_date' => $dnsEntry['changed_date'],
                           'status' => 'ACTIVE');

            $this->db->buildSql()->table('dns')->insert($entry)->execute();
            $entryId = $this->db->getInsertId();
        }

        return $entryId;
    }

    private function getAccounts($ids = array())
    {
        $sql = $this->db->buildSql()->table('accounts');

        if(count($ids))
        {
            $sql->where(array('id' => $ids));
        }

        return $sql->execute();
    }
}