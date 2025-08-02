<?php

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $conn;

    public function __construct() {
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
            $this->conn->query("SET time_zone = '+05:30'");
        } catch (Exception $e) {
            // Log the error to a file
            file_put_contents(__DIR__ . '/../../db_errors.log', $e->getMessage() . "\n", FILE_APPEND);
            // Prevent the application from continuing without a database connection
            die("Database connection failed. Please check the logs for details.");
        }
    }

    public function query($sql, $params = []) {
        $stmt = $this->conn->prepare($sql);
        
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $this->conn->error);
        }
        
        if (!empty($params)) {
            $types = $this->getParamTypes($params);
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Execute failed: " . $error);
        }
        
        $result = $stmt->get_result();
        $stmt->close();
        
        return $result;
    }
    
    private function getParamTypes($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } elseif (is_string($param)) {
                $types .= 's';
            } else {
                $types .= 'b'; // blob
            }
        }
        return $types;
    }

    public function getLastInsertId() {
        return $this->conn->insert_id;
    }
}
