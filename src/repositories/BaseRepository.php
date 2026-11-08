<?php
abstract class BaseRepository
{
    protected mysqli $db;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
    }

    protected function fetchOne(mysqli_stmt $statement): array | false
    {
        $statement->execute();
        return $statement->get_result()->fetch_assoc() ?: false;
    }
}