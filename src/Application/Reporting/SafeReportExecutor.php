<?php
declare(strict_types=1);

namespace App\Application\Reporting;

use App\Infrastructure\Database\PdoFactory;
use InvalidArgumentException;
use PDO;

/**
 * Ejecuta consultas definidas visualmente con lista blanca estricta (sin SQL arbitrario del cliente).
 */
final class SafeReportExecutor
{
    private const MAX_ROWS = 10000;

    public function __construct(private readonly ReportSchemaRegistry $registry)
    {
    }

    /**
     * @param array<string, mixed> $spec
     * @return array{columns: list<string>, rows: list<array<string, mixed>>}
     */
    public function run(array $spec): array
    {
        $datasetKey = (string)($spec['dataset'] ?? 'sales_detail');
        $ds = $this->registry->getDataset($datasetKey);
        $fields = $ds['fields'];
        $from = $ds['from'];
        $dateCol = $ds['date_column'];

        $dimensions = $spec['dimensions'] ?? [];
        if (!is_array($dimensions)) {
            $dimensions = [];
        }
        $measures = $spec['measures'] ?? [];
        if (!is_array($measures)) {
            $measures = [];
        }
        $filters = $spec['filters'] ?? [];
        if (!is_array($filters)) {
            $filters = [];
        }

        $selectParts = [];
        $groupParts = [];

        foreach ($dimensions as $dim) {
            $key = is_array($dim) ? (string)($dim['field'] ?? '') : (string)$dim;
            if ($key === '' || !isset($fields[$key])) {
                throw new InvalidArgumentException('Dimension no permitida: ' . $key);
            }
            $alias = $this->sanitizeAlias(is_array($dim) ? ($dim['alias'] ?? $key) : $key);
            $expr = $fields[$key];
            $selectParts[] = $expr . ' AS `' . $alias . '`';
            $groupParts[] = $expr;
        }

        $allowedAgg = $this->registry->allowedAggregates();
        foreach ($measures as $m) {
            if (!is_array($m)) {
                continue;
            }
            $fn = strtoupper((string)($m['fn'] ?? ''));
            $field = (string)($m['field'] ?? '');
            $alias = $this->sanitizeAlias((string)($m['alias'] ?? 'measure'));
            if (!in_array($fn, $allowedAgg, true)) {
                throw new InvalidArgumentException('Agregacion no permitida');
            }
            if ($fn === 'COUNT' && ($field === '' || $field === '*')) {
                $selectParts[] = 'COUNT(1) AS `' . $alias . '`';
                continue;
            }
            if (!isset($fields[$field])) {
                throw new InvalidArgumentException('Campo de medida no permitido: ' . $field);
            }
            $selectParts[] = $fn . '(' . $fields[$field] . ') AS `' . $alias . '`';
        }

        if ($selectParts === []) {
            throw new InvalidArgumentException('Debe haber al menos una dimension o medida');
        }

        $where = [];
        $params = [];

        $dateFrom = trim((string)($spec['date_from'] ?? ''));
        $dateTo = trim((string)($spec['date_to'] ?? ''));
        if ($dateFrom !== '' && $dateTo !== '') {
            $where[] = $dateCol . ' >= :df AND ' . $dateCol . ' <= :dt';
            $params[':df'] = $dateFrom . ' 00:00:00';
            $params[':dt'] = $dateTo . ' 23:59:59';
        }

        $fIdx = 0;
        foreach ($filters as $f) {
            if (!is_array($f)) {
                continue;
            }
            $field = (string)($f['field'] ?? '');
            $op = strtolower((string)($f['op'] ?? 'eq'));
            if (!isset($fields[$field])) {
                throw new InvalidArgumentException('Filtro campo no permitido: ' . $field);
            }
            $col = $fields[$field];
            $param = ':f' . $fIdx++;
            match ($op) {
                'eq' => $this->appendWhere($where, $col . ' = ' . $param, $params, $param, $f['value'] ?? null),
                'ne' => $this->appendWhere($where, $col . ' <> ' . $param, $params, $param, $f['value'] ?? null),
                'gt' => $this->appendWhere($where, $col . ' > ' . $param, $params, $param, $f['value'] ?? null),
                'gte' => $this->appendWhere($where, $col . ' >= ' . $param, $params, $param, $f['value'] ?? null),
                'lt' => $this->appendWhere($where, $col . ' < ' . $param, $params, $param, $f['value'] ?? null),
                'lte' => $this->appendWhere($where, $col . ' <= ' . $param, $params, $param, $f['value'] ?? null),
                'like' => $this->appendWhere($where, $col . ' LIKE ' . $param, $params, $param, $f['value'] ?? null),
                'between' => $this->appendBetween($where, $params, $col, (string)($f['value'] ?? ''), $fIdx),
                default => throw new InvalidArgumentException('Operador de filtro no permitido'),
            };
        }

        $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM ' . $from;
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        if ($groupParts !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $groupParts);
        }

        $orderBy = (string)($spec['order_by'] ?? '');
        $orderDir = strtoupper((string)($spec['order_dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        if ($orderBy !== '') {
            $allowedOrder = [];
            foreach ($dimensions as $dim) {
                $k = is_array($dim) ? (string)($dim['field'] ?? '') : (string)$dim;
                $a = $this->sanitizeAlias(is_array($dim) ? ($dim['alias'] ?? $k) : $k);
                $allowedOrder[] = $a;
            }
            foreach ($measures as $m) {
                if (is_array($m) && isset($m['alias'])) {
                    $allowedOrder[] = $this->sanitizeAlias((string)$m['alias']);
                }
            }
            $obSan = $this->sanitizeAlias($orderBy);
            if (in_array($obSan, $allowedOrder, true)) {
                $sql .= ' ORDER BY `' . $obSan . '` ' . $orderDir;
            }
        }

        $limit = (int)($spec['limit'] ?? self::MAX_ROWS);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > self::MAX_ROWS) {
            $limit = self::MAX_ROWS;
        }
        $sql .= ' LIMIT ' . $limit;

        $pdo = PdoFactory::get();
        $st = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v);
        }
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $columns = $rows === [] ? [] : array_keys($rows[0]);

        return ['columns' => $columns, 'rows' => $rows];
    }

    /** @param array<string, mixed> $params */
    private function appendWhere(array &$where, string $fragment, array &$params, string $param, mixed $value): void
    {
        $where[] = $fragment;
        $params[$param] = $value;
    }

    /** @param array<string, mixed> $params */
    private function appendBetween(array &$where, array &$params, string $col, string $value, int &$fIdx): void
    {
        $parts = explode('|', $value, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Between requiere valor|valor');
        }
        $p1 = ':b' . $fIdx++;
        $p2 = ':b' . $fIdx++;
        $params[$p1] = trim($parts[0]);
        $params[$p2] = trim($parts[1]);
        $where[] = $col . ' BETWEEN ' . $p1 . ' AND ' . $p2;
    }

    private function sanitizeAlias(string $alias): string
    {
        $alias = trim($alias);
        if ($alias === '' || !preg_match('/^[a-zA-Z0-9_]{1,64}$/', $alias)) {
            throw new InvalidArgumentException('Alias invalido');
        }
        return $alias;
    }
}
