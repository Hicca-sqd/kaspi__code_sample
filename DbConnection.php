<?php 

	
	class DbConnection{

		private $dbh;
		private $configs;

		function __construct()
        {
			$this->configs = parse_ini_file(__DIR__."/configs.ini", true);
			$this->dbConnect();
		}

		function dbConnect()
        {
			try{				
				$user = $this->configs["database"]["dbUser"];
				$pass = $this->configs["database"]["dbPass"];
				$host =  $this->configs["database"]["host"];
				$dbname =  $this->configs["database"]["dbName"];
				$this->dbh = new PDO("mysql:host={$host};dbname={$dbname}", "$user", "$pass");
			} catch (PDOException $e) {
			    print "Error!: " . $e->getMessage() . "<br/>";
			}
		}

		 function insertDeal(KaspiOrder $order, $dealid, $category)
         {
			$sql = "INSERT INTO Deals (orderCode, orderId, dealId, dealCategory, contactId, formattedAddress, deliveryMode, paymentMode, firstName, lastName, phone, isPreorder) VALUES (:orderCode, :orderId, :dealId, :category, :contact_id, :formattedAddress, :deliveryMethod, :paymentMethod, :firstName, :lastName, :phone, :preorder);";
			$statement = $this->dbh->prepare($sql);
			$statement->execute([
			    'orderCode' => $order->orderId,
			    'orderId' => $order->id,
			    'dealId' => $dealid,
			    'category' => $category,
			    'contact_id' => $order->contact_id,
			    'formattedAddress' => $order->formattedAddress,
			    'deliveryMethod' => $order->deliverymode,
				'paymentMethod' => $order->paymentMode,
				'firstName' => $order->firstName,
				'lastName' => $order->lastName,
				'phone' => $order->phone,
				'preorder' => $order->is_preorder
			]);

		}

		function searchProducts($good)
        {
            $sku = $good["sku"];
            $sql = "SELECT Ssylka_UID_Sylka FROM nomenklatura where Kod LIKE '$sku'";
            $statement = $this->dbh->prepare($sql);
            $statement->execute();
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $result[0]['Ssylka_UID_Sylka'];
        }

		function insertProducts($args, $dealId)
        {
            $goodUID = $this->searchProducts($args);
			$sql = "INSERT INTO DealGoods (quantity, goodUID, basePrice,  dealId) VALUES (:quantity, :goodUID, :basePrice, :dealId);";
			$statement = $this->dbh->prepare($sql);
			$statement->execute([
			    'quantity' => $args["quantity"],
			    'basePrice' => $args["basePrice"],
				'goodUID' => $goodUID,
				"dealId" => $dealId
			]);

		}

	}
 ?>