<?php

$migrationsPath = __DIR__ . '/database/migrations';
$modelsPath = __DIR__ . '/app/Models';

$migrations = glob($migrationsPath . '/*.php');
$models = glob($modelsPath . '/*.php');
// Also get nested models
$nestedModels = glob($modelsPath . '/**/*.php');
if ($nestedModels) {
    $models = array_merge($models, $nestedModels);
}

$tables = [];
$foreignKeys = [];
$modelRels = [];

// Analyze migrations
foreach ($migrations as $file) {
    $content = file_get_contents($file);
    
    // Find Schema::create
    preg_match_all("/Schema::create\('([^']+)',\s*function\s*\(Blueprint\s+\$(\w+)\)(.*?)\}/s", $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $table = $match[1];
        $var = $match[2];
        $body = $match[3];
        
        $pk = 'unknown';
        $dbType = 'unknown';
        
        if (preg_match("/\$\w+->id\(\)/", $body)) {
            $pk = 'id';
            $dbType = 'BIGINT unsigned';
        } elseif (preg_match("/\$\w+->uuid\('([^']+)'\)/", $body, $m)) {
            $pk = $m[1];
            $dbType = 'UUID (CHAR(36))';
        } elseif (preg_match("/\$\w+->string\('([^']+)'\)->primary\(\)/", $body, $m)) {
            $pk = $m[1];
            $dbType = 'VARCHAR';
        } elseif (preg_match("/\$\w+->primary\(\[([^\]]+)\]/", $body, $m)) {
            $pk = "composite: " . trim($m[1]);
            $dbType = 'composite';
        }

        $tables[$table] = [
            'pk' => $pk,
            'dbType' => $dbType,
        ];
        
        // Find foreign keys
        // foreignId('...')->constrained('...')->nullOnDelete()
        // foreignUuid('...')
        // foreign('...')->references('...')->on('...')
        
        preg_match_all("/\$\w+->(?:foreignId|foreignUuid|unsignedBigInteger|uuid)\('([^']+)'\)/", $body, $fkMatches, PREG_SET_ORDER);
        foreach ($fkMatches as $fkm) {
            $col = $fkm[1];
            // Look around for constrained or nullable
            // It's a bit complex with regex, let's do simple scanning
            $line = '';
            $lines = explode("
", $body);
            foreach ($lines as $l) {
                if (strpos($l, "'$col'") !== false) {
                    $line = $l;
                    break;
                }
            }
            
            $nullable = strpos($line, 'nullable()') !== false ? 'Yes' : 'No';
            $onDelete = '';
            if (strpos($line, 'cascadeOnDelete()') !== false) $onDelete = 'CASCADE';
            elseif (strpos($line, 'nullOnDelete()') !== false) $onDelete = 'SET NULL';
            elseif (strpos($line, 'restrictOnDelete()') !== false) $onDelete = 'RESTRICT';
            elseif (preg_match("/onDelete\('([^']+)'\)/", $line, $om)) $onDelete = strtoupper($om[1]);
            
            $references = 'unknown';
            if (preg_match("/constrained\('([^']+)'\)/", $line, $cm)) {
                $references = $cm[1] . ".id";
            } elseif (preg_match("/constrained\(\)/", $line)) {
                // Infer from name e.g. user_id -> users
                $inf = str_replace('_id', 's', $col);
                if ($inf == 'categorie_s') $inf = 'categories';
                $references = $inf . ".id";
            }
            
            if ($col !== 'id') {
                 $foreignKeys[] = [
                    'table' => $table,
                    'col' => $col,
                    'ref' => $references,
                    'nullable' => $nullable,
                    'on_delete' => $onDelete,
                 ];
            }
        }
        
        // Check for explicit foreign()
        preg_match_all("/\$\w+->foreign\((?:'([^']+)'|\[([^\]]+)\])\)->references\('([^']+)'\)->on\('([^']+)'\)(.*?;)/", $body, $exFkMatches, PREG_SET_ORDER);
        foreach ($exFkMatches as $efk) {
            $col = $efk[1] ?: trim($efk[2], " '\\\"");
            $refCol = $efk[3];
            $refTable = $efk[4];
            $rest = $efk[5];
            
            $onDelete = '';
            if (preg_match("/onDelete\('([^']+)'\)/", $rest, $om)) {
                $onDelete = strtoupper($om[1]);
            }
            
            $foreignKeys[] = [
                'table' => $table,
                'col' => $col,
                'ref' => "$refTable.$refCol",
                'nullable' => 'Check Column',
                'on_delete' => $onDelete,
            ];
        }
    }
}

// Analyze Models
foreach ($models as $file) {
    $content = file_get_contents($file);
    $modelName = basename($file, '.php');
    
    // Find relationship methods: belongsTo, hasMany, hasOne, belongsToMany, morphTo, morphMany
    preg_match_all("/public\s+function\s+(\w+)\s*\(\).*?\{(.*?return\s+\$this->(belongsTo|hasMany|hasOne|belongsToMany|morphTo|morphMany)\(.*?;).*?\}/s", $content, $matches, PREG_SET_ORDER);
    
    foreach ($matches as $match) {
        $method = $match[1];
        $type = $match[3];
        $modelRels[$modelName][] = [
            'method' => $method,
            'type' => $type
        ];
    }
}

// Generate Output

echo "## Primary Keys by Table
";
echo "| Table | Primary Key | DB Type | App Format | Notes |
";
echo "|---|---|---|---|---|
";
foreach ($tables as $t => $info) {
    $appFormat = $info['dbType'] === 'BIGINT unsigned' ? 'Integer ID' : ($info['dbType'] === 'composite' ? 'Composite' : 'String');
    echo "| $t | {$info['pk']} | {$info['dbType']} | $appFormat | Standard Laravel PK strategy |
";
}

echo "
## Foreign Key Associations
";
echo "| Table | FK Column | References | Nullable | On Delete/Update | Notes |
";
echo "|---|---|---|---|---|---|
";
foreach ($foreignKeys as $fk) {
    echo "| {$fk['table']} | {$fk['col']} | {$fk['ref']} | {$fk['nullable']} | {$fk['on_delete']} | |
";
}

echo "
## Laravel Model Relationship Review
";
foreach ($modelRels as $model => $rels) {
    echo "- **$model**
";
    foreach ($rels as $rel) {
        echo "  - `{$rel['method']}()` -> `{$rel['type']}`
";
    }
}
