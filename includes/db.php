<?php

class Database {
    private $host;
    private $user;
    private $pass;
    private $dbname;
    private $conn;

    public function __construct() {
        $this->host = DB_HOST;
        $this->user = DB_USER;
        $this->pass = DB_PASS;
        $this->dbname = DB_NAME;

        // Enable exceptions for mysqli errors for more robust error handling
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->dbname);
            // It's best practice to set the charset after connecting to prevent encoding issues
            $this->conn->set_charset("utf8mb4");
            $this->conn->query("SET time_zone = '+05:30'");
        } catch (\mysqli_sql_exception $e) {
            // Log the error to a file
            error_log("Database connection failed: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
            // Prevent the application from continuing without a database connection
            // Use a 503 Service Unavailable status code
            http_response_code(503);
            die("A critical database error occurred. The site is temporarily unavailable. Please check the server logs for details.");
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
        
        // For INSERT/UPDATE/DELETE statements, get_result() returns false
        // We need to handle this case differently
        $result = $stmt->get_result();
        if ($result === false) {
            // This is likely an INSERT/UPDATE/DELETE statement
            // Store affected rows count before closing the statement
            $affectedRows = $this->conn->affected_rows;
            $stmt->close();
            // Return the affected rows count for these statements
            return $affectedRows;
        }
        
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

    public function getAffectedRows() {
        return $this->conn->affected_rows;
    }

    public function getLastInsertId() {
        return $this->conn->insert_id;
    }

    public function begin_transaction() {
        return $this->conn->begin_transaction();
    }

    public function commit() {
        return $this->conn->commit();
    }

    public function rollback() {
        return $this->conn->rollback();
    }

    public function inTransaction() {
        return $this->conn->in_transaction;
    }

    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
