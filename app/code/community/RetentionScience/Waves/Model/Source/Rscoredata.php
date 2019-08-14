<?php

class RetentionScience_Waves_Model_Source_Rscoredata {

    protected $resource;
    protected $readConnection;

    public function getDataArray()
    {
        Mage::getSingleton('waves/connection_awsCloudWatch')->logMessage("getDataArray called in Rscore");

        $this->resource = Mage::getSingleton('core/resource');
        $this->readConnection = $this->resource->getConnection('core_read');

        return array(   "name" => Mage::getStoreConfig('general/store_information/name'),
            "url" => Mage::getStoreConfig('web/secure/base_url'),
            "source" => "magento",
            "extra_info" => array(
                "phone" => Mage::getStoreConfig('general/store_information/phone'),
                "email" => Mage::getStoreConfig('trans_email/ident_general/email'),
                "num_users" => Mage::getModel('customer/customer')->getCollection()->getSize(),
                "num_orders" => Mage::getModel('sales/order')->getCollection()->getSize()
            ),
            "rscore_metrics" => array(
                "churn_array" => $this->getChurnArray(),
                "engagement_rate" => $this->getEngagementRate(),
                "rpr_rate" => $this->getRprRate()));
    }

    private function getChurnArray()
    {
        $customer_table = $this->resource->getTableName('customer_entity');
        $order_table = $this->resource->getTableName('sales_flat_order');
        $query = "  SELECT days, COUNT(DISTINCT customer_id) AS c
                    FROM (
                        SELECT o.customer_id, DATEDIFF(MAX(o.created_at), MIN(c.created_at)) AS days, COUNT(o.entity_id) AS ct
                        FROM $order_table o INNER JOIN $customer_table c ON o.customer_id = c.entity_id
                        GROUP BY c.entity_id) a
                    WHERE ct > 1 AND days >= 0 AND days < 365
                    GROUP BY days
                    ORDER BY days ASC;";
        return array_map("array_values", $this->readConnection->fetchAll($query));
    }

    private function getEngagementRate()
    {
        $customer_table = $this->resource->getTableName('customer_entity');
        $order_table = $this->resource->getTableName('sales_flat_order');
        $query = "SELECT
                    (SELECT COUNT(DISTINCT customer_email) FROM $order_table) /
                    (SELECT count(*) FROM ((SELECT email FROM $customer_table) UNION (SELECT customer_email FROM $order_table WHERE customer_is_guest IS TRUE)) a);";
        return $this->readConnection->fetchOne($query);
    }

    private function getRprRate()
    {
        $writeConnection = $this->resource->getConnection('core_read');
        $customer_table = $this->resource->getTableName('customer_entity');
        $order_table = $this->resource->getTableName('sales_flat_order');
        $writeConnection->query("DROP TEMPORARY TABLE IF EXISTS _temp_orders_1;");
        $writeConnection->query("DROP TEMPORARY TABLE IF EXISTS _temp_orders_2;");
        $writeConnection->query("   CREATE TEMPORARY TABLE _temp_orders_1
                                    (
                                        customer_email varchar(255),
                                        created_at datetime,
                                        key(customer_email)
                                    ) AS
                                    (
                                        SELECT customer_email, created_at FROM $order_table
                                    );");
        $writeConnection->query("   CREATE TEMPORARY TABLE _temp_orders_2
                                    (
                                        customer_email varchar(255),
                                        created_at datetime,
                                        key(customer_email)
                                    ) AS
                                    (
                                        SELECT customer_email, created_at FROM _temp_orders_1
                                    );");
        $query = "  SELECT yearmonth, SUM(repeat_purchase) AS repeat_users, COUNT(*) AS total_users
                    FROM (
                        SELECT o.customer_email, DATE_FORMAT(o.created_at, '%Y%m') AS yearmonth, (first_orders.first_order_date < MAX(o.created_at)) AS repeat_purchase
                        FROM _temp_orders_1 o
                            INNER JOIN (
                                SELECT customer_email, MIN(created_at) AS first_order_date
                                FROM _temp_orders_2
                                WHERE customer_email IS NOT NULL
                                GROUP BY customer_email) first_orders
                            ON o.customer_email = first_orders.customer_email
                        GROUP BY o.customer_email, yearmonth) order_users
                    GROUP BY yearmonth;";
        $results = $writeConnection->fetchAll($query);
        array_shift($results); // ignore first month

        if (count($results) == 0) {
            return null;
        } else {
            $rates = array();
            foreach ($results as $row)
            {
                $repeat_count = (float) $row['repeat_users'];
                $one_time_count = (float) $row['total_users'] - $repeat_count;
                array_push($rates, ($one_time_count > 0) ? $repeat_count/$one_time_count : 1);
            }
            return array_sum($rates) / count($rates);
        }
    }

}