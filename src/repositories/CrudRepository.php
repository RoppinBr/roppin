<?php
require_once __DIR__ . '/BaseRepository.php';
require_once __DIR__ . '/../models/Specialty.php';
require_once __DIR__ . '/../models/Requester.php';
require_once __DIR__ . '/../models/Provider.php';
require_once __DIR__ . '/../models/ServiceCall.php';
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Review.php';
require_once __DIR__ . '/../models/Message.php';

class CrudRepository extends BaseRepository
{
    private const MODEL_CLASSES = [
        Specialty::class,
        Requester::class,
        Provider::class,
        ServiceCall::class,
        Payment::class,
        Review::class,
        Message::class
    ];

    public function findById(string $modelClass, int | string $id): Model | false
    {
        $metadata = $this->metadata($modelClass);
        $statement = $this->db->prepare('SELECT * FROM `' . $metadata['table'] . '` WHERE `' . $metadata['primaryKey'] . '` = ? LIMIT 1');
        $statement->bind_param(is_int($id) ? 'i' : 's', $id);
        $row = $this->fetchOne($statement);

        return $row ? new $modelClass($row) : false;
    }

    public function findAll(string $modelClass, int $limit = 100, int $offset = 0): array
    {
        $metadata = $this->metadata($modelClass);
        $limit = max(1, min($limit, 500));
        $offset = max(0, $offset);
        $statement = $this->db->prepare('SELECT * FROM `' . $metadata['table'] . '` ORDER BY `' . $metadata['primaryKey'] . '` LIMIT ? OFFSET ?');
        $statement->bind_param('ii', $limit, $offset);
        $statement->execute();

        $models = [];
        $result = $statement->get_result();
        while ($row = $result->fetch_assoc()) {
            $models[] = new $modelClass($row);
        }

        return $models;
    }

    public function create(string $modelClass, array $attributes): Model | false
    {
        $metadata = $this->metadata($modelClass);
        $this->assertAllowedFields($attributes, $metadata['fields']);
        $fields = array_values(array_intersect($metadata['fields'], array_keys($attributes)));

        if ($metadata['autoIncrement']) {
            $fields = array_values(array_diff($fields, [$metadata['primaryKey']]));
        } elseif (!array_key_exists($metadata['primaryKey'], $attributes)) {
            throw new InvalidArgumentException('O model exige a chave primária para criação.');
        }

        if ($fields === []) {
            throw new InvalidArgumentException('Nenhum campo foi informado para criação.');
        }

        $columns = array_map(static fn(string $field): string => '`' . $field . '`', $fields);
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $statement = $this->db->prepare('INSERT INTO `' . $metadata['table'] . '` (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
        $values = array_map(static fn(string $field) => $attributes[$field], $fields);
        $this->bindValues($statement, $values);
        $statement->execute();

        $id = $metadata['autoIncrement'] ? $statement->insert_id : $attributes[$metadata['primaryKey']];
        return $this->findById($modelClass, $id);
    }

    public function update(string $modelClass, int | string $id, array $attributes): Model | false
    {
        $metadata = $this->metadata($modelClass);
        $this->assertAllowedFields($attributes, $metadata['fields']);
        $fields = array_values(array_diff(array_intersect($metadata['fields'], array_keys($attributes)), [$metadata['primaryKey']]));

        if ($fields === []) {
            throw new InvalidArgumentException('Nenhum campo atualizável foi informado.');
        }

        $assignments = array_map(static fn(string $field): string => '`' . $field . '` = ?', $fields);
        $statement = $this->db->prepare('UPDATE `' . $metadata['table'] . '` SET ' . implode(', ', $assignments) . ' WHERE `' . $metadata['primaryKey'] . '` = ?');
        $values = array_map(static fn(string $field) => $attributes[$field], $fields);
        $values[] = $id;
        $this->bindValues($statement, $values);
        $statement->execute();

        return $this->findById($modelClass, $id);
    }

    public function delete(string $modelClass, int | string $id): bool
    {
        $metadata = $this->metadata($modelClass);
        $statement = $this->db->prepare('DELETE FROM `' . $metadata['table'] . '` WHERE `' . $metadata['primaryKey'] . '` = ?');
        $statement->bind_param(is_int($id) ? 'i' : 's', $id);
        $statement->execute();

        return $statement->affected_rows === 1;
    }

    private function metadata(string $modelClass): array
    {
        if (!in_array($modelClass, self::MODEL_CLASSES, true)) {
            throw new InvalidArgumentException('Model não habilitado para CRUD.');
        }

        $table = $modelClass::table();
        $primaryKey = $modelClass::primaryKey();
        $fields = $modelClass::fields();

        foreach (array_merge([$table, $primaryKey], $fields) as $identifier) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
                throw new LogicException('Identificador SQL inválido no model.');
            }
        }

        return [
            'table' => $table,
            'primaryKey' => $primaryKey,
            'fields' => $fields,
            'autoIncrement' => $modelClass::hasAutoIncrement()
        ];
    }

    private function assertAllowedFields(array $attributes, array $allowedFields): void
    {
        $unknownFields = array_diff(array_keys($attributes), $allowedFields);
        if ($unknownFields !== []) {
            throw new InvalidArgumentException('O model recebeu campos não permitidos.');
        }
    }

    private function bindValues(mysqli_stmt $statement, array &$values): void
    {
        $types = '';
        foreach ($values as $value) {
            $types .= is_int($value) || is_bool($value) ? 'i' : (is_float($value) ? 'd' : 's');
        }

        $parameters = [$types];
        foreach ($values as &$value) {
            $parameters[] = &$value;
        }
        unset($value);

        call_user_func_array([$statement, 'bind_param'], $parameters);
    }
}