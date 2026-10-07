<?php

declare(strict_types=1);

namespace Plugin\HeaderFooterBuilder\Repository;

use InvalidArgumentException;
use JsonException;
use PDO;
use Plugin\HeaderFooterBuilder\Support\Conditions;
use Plugin\HeaderFooterBuilder\Support\Html;
use Plugin\HeaderFooterBuilder\Support\Layouts;
use Qubus\Exception\Exception;
use Qubus\Expressive\Database;
use Qubus\Expressive\Schema\CreateTable;
use Random\RandomException;

final readonly class BuilderRepository
{
    public function __construct(private Database $dfdb)
    {
    }

    /**
     * @throws Exception
     */
    public function migrate(): void
    {
        foreach (['hfb_template', 'hfb_menu'] as $name) {
            $tableName = $this->dfdb->prefix . $name;
            if ($this->dfdb->schema()->hasTable($tableName)) {
                continue;
            }
            $this->dfdb->schema()->create($tableName, static function (CreateTable $table) use ($name): void {
                $table->string('id', 36)->primary();
                $table->string('name', 191)->notNull();
                $table->text('payload')->size('big');
                if ($name === 'hfb_template') {
                    $table->string('kind', 20)->notNull();
                    $table->integer('published')->notNull()->defaultValue(0);
                    $table->integer('priority')->notNull()->defaultValue(0);
                    $table->text('conditions')->size('big');
                }
            });
        }
    }

    private function execute(string $sql, array $values = []): \PDOStatement
    {
        $statement = $this->dfdb->getConnection()->pdo->prepare($sql);
        $statement->execute($values);
        return $statement;
    }

    public function templates(): array
    {
        return array_map($this->decodeTemplate(...), $this->execute(
            "SELECT * FROM {$this->dfdb->prefix}hfb_template ORDER BY name, id"
        )->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * @throws JsonException
     */
    private function decodeTemplate(array $row): array
    {
        $row['data'] = json_decode($row['payload'] ?? '{}', true, 512, JSON_THROW_ON_ERROR) ?: [];
        $row['conditions'] = json_decode($row['conditions'] ?? '{}', true, 512, JSON_THROW_ON_ERROR) ?: [];
        $row['priority'] = (int) $row['priority'];
        $row['published'] = (bool) $row['published'];
        unset($row['payload']);
        return $row;
    }

    /**
     * @throws JsonException
     */
    public function template(string $id): ?array
    {
        $row = $this
            ->execute("SELECT * FROM {$this->dfdb->prefix}hfb_template WHERE id = ?", [$id])
            ->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->decodeTemplate($row) : null;
    }

    /**
     * @throws RandomException
     * @throws JsonException
     */
    public function saveTemplate(array $input): string
    {
        $id = trim((string) ($input['id'] ?? ''));
        $existing = $id === '' ? null : $this->template($id);
        if ($id !== '' && $existing === null) {
            throw new InvalidArgumentException('Template not found.');
        }
        $kind = (string) ($input['kind'] ?? 'header');
        $name = trim((string) ($input['name'] ?? ''));
        if (!in_array($kind, ['header', 'footer', 'mega'], true) || $name === '' || mb_strlen($name) > 191) {
            throw new InvalidArgumentException('Enter a name of up to 191 characters and a valid template type.');
        }
        if ($existing && $existing['kind'] !== $kind) {
            throw new InvalidArgumentException('The template type cannot be changed.');
        }
        $conditions = Conditions::normalize($input['conditions'] ?? []);
        $values = [$name, $kind, !empty($input['published']) ? 1 : 0,
            max(-1000, min(1000, (int) ($input['priority'] ?? 0))), json_encode($conditions, JSON_THROW_ON_ERROR)];
        if ($existing) {
            $this->execute("UPDATE {$this->dfdb->prefix}hfb_template SET name=?, kind=?, published=?, priority=?, conditions=? WHERE id=?", [...$values, $id]);
        } else {
            $id = bin2hex(random_bytes(16));
            $this->execute("INSERT INTO {$this->dfdb->prefix}hfb_template (name,kind,published,priority,conditions,id,payload) VALUES (?,?,?,?,?,?,?)", [
                ...$values, $id, json_encode(Layouts::data($kind, (string) ($input['layout'] ?? 'blank')), JSON_THROW_ON_ERROR)
            ]);
        }
        return $id;
    }

    /**
     * @throws JsonException
     */
    public function saveData(string $id, array $data): bool
    {
        if ($this->template($id) === null) {
            throw new InvalidArgumentException('Template not found.');
        }
        $this->execute(
            "UPDATE {$this->dfdb->prefix}hfb_template SET payload=? WHERE id=?",
            [json_encode($data, JSON_THROW_ON_ERROR), $id]
        );
        return true;
    }

    public function deleteTemplate(string $id): void
    {
        $this->execute("DELETE FROM {$this->dfdb->prefix}hfb_template WHERE id=?", [$id]);
    }

    /**
     * @throws JsonException
     */
    public function menus(): array
    {
        $rows = $this
            ->execute("SELECT * FROM {$this->dfdb->prefix}hfb_menu ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['items'] = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR) ?: [];
            unset($row['payload']);
        }
        return $rows;
    }

    /**
     * @throws JsonException
     */
    public function menu(string $id): ?array
    {
        return array_find($this->menus(), fn($menu) => $menu['id'] === $id);
    }

    /**
     * @throws RandomException
     * @throws JsonException
     */
    public function saveMenu(array $input): string
    {
        $id = trim((string) ($input['id'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '' || mb_strlen($name) > 191) {
            throw new InvalidArgumentException('Enter a menu name of up to 191 characters.');
        }

        if ($id !== '' && $this->menu($id) === null) {
            throw new InvalidArgumentException('Menu not found.');
        }

        $items = self::normalizeItems($input['items'] ?? []);
        foreach ($items as $item) {
            if ($item['template'] !== '') {
                $template = $this->template($item['template']);
                if (!$template || $template['kind'] !== 'mega') {
                    throw new InvalidArgumentException('Choose a mega menu template.');
                }
            }
        }
        $payload = json_encode($items, JSON_THROW_ON_ERROR);
        if ($id === '') {
            $id = bin2hex(random_bytes(16));
            $this->execute(
                "INSERT INTO {$this->dfdb->prefix}hfb_menu (id,name,payload) VALUES (?,?,?)",
                [$id, $name, $payload]
            );
        } else {
            $this->execute(
                "UPDATE {$this->dfdb->prefix}hfb_menu SET name=?,payload=? WHERE id=?",
                [$name, $payload, $id]
            );
        }
        return $id;
    }

    public static function normalizeItems(array $items, int $depth = 0): array
    {
        if ($depth > 8 || count($items) > 100) {
            throw new InvalidArgumentException('Menus support up to 100 items per level and 8 nested levels.');
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item) || trim((string) ($item['label'] ?? '')) === '') {
                continue;
            }
            if (!is_array($item['children'] ?? [])) {
                throw new InvalidArgumentException('Menu children must be a list.');
            }
            $result[] = [
                'label' => mb_substr(trim((string) $item['label']), 0, 191),
                'url' => Html::url($item['url'] ?? '#'),
                'target' => ($item['target'] ?? '') === '_blank' ? '_blank' : '_self',
                'template' => $depth === 0 ? (string) ($item['template'] ?? '') : '',
                'trigger' => ($item['trigger'] ?? '') === 'click' ? 'click' : 'hover',
                'width_mode' => in_array($item['width_mode'] ?? '', ['full', 'custom'], true) ? $item['width_mode'] : 'default',
                'width' => Html::length($item['width'] ?? '600px', '600px'),
                'mobile_width' => Html::length($item['mobile_width'] ?? '100%', '100%'),
                'align' => in_array($item['align'] ?? '', ['center', 'right'], true) ? $item['align'] : 'left',
                'ajax' => !empty($item['ajax']),
                'children' => self::normalizeItems($item['children'] ?? [], $depth + 1),
            ];
        }
        return $result;
    }

    public function deleteMenu(string $id): void
    {
        $this->execute("DELETE FROM {$this->dfdb->prefix}hfb_menu WHERE id=?", [$id]);
    }

    public function targets(): array
    {
        $pages = $this
            ->execute("SELECT id, name FROM {$this->dfdb->prefix}pages ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);
        $types = $this
            ->execute("SELECT content_type_slug AS id, content_type_title AS name FROM {$this->dfdb->prefix}content_type ORDER BY content_type_title")
            ->fetchAll(PDO::FETCH_ASSOC);
        return ['pages' => $pages, 'content_types' => $types];
    }

    public function contentTypeForPath(string $path): string
    {
        $segments = explode('/', trim(rawurldecode($path), '/'));
        $slug = end($segments);
        if ($slug === '') {
            return '';
        }
        $rows = $this
            ->execute("SELECT content_type FROM {$this->dfdb->prefix}content WHERE content_slug = ? AND content_status = 'published'", [$slug])
            ->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $type = (string) $row['content_type'];
            if (trim($path, '/') === $type . '/' . $slug || (count($segments) === 1 && count($rows) === 1)) {
                return $type;
            }
        }
        return '';
    }
}
