<?php

class PHP_CRUD_UI
{

    protected $settings;

    public function call($method, $url, $data = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_URL, $url);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $headers = array();
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function url($base, $subject, $action, $id = '', $field = '')
    {
        return $base . trim("$subject/$action/$id/$field", '/');
    }

    public function menu()
    {
        extract($this->settings);
        $subject = $this->getParameter(0);

        $html = '<ul class="nav nav-pills nav-stacked">';
        if (isset($definition['tags'])) {
            foreach ($definition['tags'] as $tag) {
                $active = $tag['name'] == $subject ? ' class="active"' : '';
                $html .= '<li' . $active . '><a href="' . $this->url($base, $tag['name'], 'list') . '">' . $tag['name'] . '</a></li>';
            }
        }
        $html .= '</ul>';
        return $html;
    }

    public function homeAction()
    {
        $html = 'Nothing';
        return $html;
    }

    public function head()
    {
        $html = '<!DOCTYPE html><html lang="en">';
        $html .= '<head><title>PHP-CRUD-UI</title>';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
        $html .= '<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet">';
        $html .= '<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap-theme.min.css" rel="stylesheet">';
        $html .= '</head><body><div class="container">';
        $html .= '<div class="row">';
        $html .= '<div class="col-md-3"><h3>PHP-CRUD-UI</h3></div>';
        $html .= '</div>';
        return $html;
    }

    public function foot()
    {
        $html = '</div></body></html>';
        return $html;
    }

    public function getDisplayColumn($columns)
    {
        // TODO: make configurable
        $names = array('name', 'title', 'description', 'username');
        foreach ($names as $name) {
            if (in_array($name, $columns)) {
                return $name;
            }

        }
        return $columns[0];
    }

    public function referenceText($subject, $record, $definition)
    {
        $properties = $this->getProperties($subject, 'read', $definition);
        $displayColumn = $this->getDisplayColumn(array_keys($properties));
        return $record[$displayColumn];
    }

    public function referenceId($subject, $record, $definition)
    {
        $properties = $this->getProperties($subject, 'read', $definition);
        $primaryKey = $this->getPrimaryKey($subject, $properties);
        return $record[$primaryKey];
    }

    public function executeList()
    {
        extract($this->settings);
        $subject = $this->getParameter(0);
        $action = $this->getParameter(1);
        $field = $this->getParameter(2);
        $id = $this->getParameter(3);

        $properties = $this->getProperties($subject, $action, $definition);
        $references = $this->getReferences($subject, $properties);
        $referenced = $this->getReferenced($subject, $properties);
        $primaryKey = $this->getPrimaryKey($subject, $properties);

        $related = !empty(array_filter($referenced));

        $args = array();
        if ($id) {
            $field = $field ?: $primaryKey;
            $args['filter'] = $field . ',eq,' . $id;
        }
        $args['join'] = array_values(array_filter($references));
        $urlArgs = rtrim('?' . preg_replace('|%5B[0-9]+%5D|', '', http_build_query($args)), '?');
        $data = $this->call('GET', $url . '/records/' . urlencode($subject) . $urlArgs);

        $html = '<h4>' . $subject . ': list</h4>';

        $href = $this->url($base, $subject, 'create');
        $html .= '<p><a href="' . $href . '" class="btn btn-primary">Add</a></p>';

        if ($field) {
            $href = $this->url($base, $subject, 'list');
            $html .= '<div class="well well-sm"><div style="float:right;"><a class="btn btn-default btn-xs" href="' . $href . '">Clear filter</a></div>';
            $html .= 'Filtered by: ' . $field . ' = ' . $id . '</div>';
        }

        $html .= '<table class="table">';
        $html .= '<thead><tr>';
        foreach (array_keys($properties) as $column) {
            $html .= '<th>' . $column . '</th>';
        }
        if ($related) {
            $html .= '<th>related</th>';
        }
        if ($primaryKey) {
            $html .= '<th>actions</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($data['records'] as $record) {
            $html .= '<tr>';
            foreach ($record as $key => $value) {
                $html .= '<td>';
                if ($references[$key]) {
                    $id = $this->referenceId($references[$key], $record[$key], $definition);
                    $href = $this->url($base, $references[$key], 'read', $id);
                    $html .= '<a href="' . $href . '">';
                    $html .= $this->referenceText($references[$key], $record[$key], $definition);
                    $html .= '</a>';
                } else {
                    $html .= $value;
                }
                $html .= '</td>';
            }
            if ($related) {
                $html .= '<td>';
                foreach ($referenced as $i => $relation) {
                    if ($i) {
                        $html .= ', ';
                    }
                    $href = $this->url($base, $relation[0], 'list', $relation[1], $record[$primaryKey]);
                    $html .= '<a href="' . $href . '">' . $relation[0] . '</a>';
                }
                $html .= '</td>';
            }
            if ($primaryKey) {
                $html .= '<td>';
                $href = $this->url($base, $subject, 'read', $record[$primaryKey]);
                $html .= '<a href="' . $href . '">view</a>';
                $html .= ' | ';
                $href = $this->url($base, $subject, 'update', $record[$primaryKey]);
                $html .= '<a href="' . $href . '">edit</a>';
                $html .= ' | ';
                $href = $this->url($base, $subject, 'delete', $record[$primaryKey]);
                $html .= '<a href="' . $href . '">delete</a>';
                $html .= '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    public function selectSubject($url, $subject, $name, $value, $definition)
    {
        $properties = $this->getProperties($subject, 'list', $definition);
        $references = $this->getReferences($subject, $properties);
        $primaryKey = $this->getPrimaryKey($subject, $properties);

        $data = $this->call('GET', $url . '/records/' . urlencode($subject));

        $displayColumn = $this->getDisplayColumn(array_keys($properties));

        $html = '<select id="' . $name . '" name="' . $name . '" class="form-control">';
        $html .= '<option value=""></option>';
        foreach ($data['records'] as $record) {
            $selected = $record[$primaryKey] == $value ? ' selected' : '';
            $html .= '<option value="' . $record[$primaryKey] . '"' . $selected . '>';
            if ($displayColumn === false) {
                $text = '';
                $first = true;
                foreach ($record as $column => $value) {
                    if (!$references[$column] && $column != $primaryKey) {
                        if (!$first) {
                            $text .= ' - ';
                        }

                        $text .= $value;
                        $first = false;
                    }
                }
                $html .= $text;
            } else {
                $html .= $record[$displayColumn];
            }
            $html .= '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    public function executeAdd()
    {
        extract($this->settings);
        $subject = $this->getParameter(0);
        $action = $this->getParameter(1);

        if ($method == 'POST') {
            $this->call('POST', $url . '/records/' . urlencode($subject), json_encode($post));
            return '<p>Added</p>';
        }

        $properties = $this->getProperties($subject, $action, $definition);
        $references = $this->getReferences($subject, $properties);
        $referenced = $this->getReferenced($subject, $properties);
        $primaryKey = $this->getPrimaryKey($subject, $properties);

        $html = '<h4>' . $subject . ': create</h4>';
        $html .= '<form method="post">';
        $data = array_keys($properties);

        foreach ($data as $column) {
            $html .= '<div class="form-group">';
            $html .= '<label for="' . $column . '">' . $column . '</label>';
            if ($references[$column]) {
                $html .= $this->selectSubject($url, $references[$column], $column, false, $definition);
            } else {
                $disabled = $column == $primaryKey ? ' disabled' : '';
                $html .= '<input class="form-control" id="' . $column . '" name="' . $column . '" value=""' . $disabled . '/>';
            }
            $html .= '</div>';
        }
        $html .= '<button type="submit" class="btn btn-primary">Save</button>';
        $html .= '</form>';
        return $html;
    }

    public function executeEdit()
    {
        extract($this->settings);
        $subject = $this->getParameter(0);
        $action = $this->getParameter(1);
        $id = $this->getParameter(2);

        if ($method == 'POST') {
            $this->call('PUT', $url . '/records/' . urlencode($subject) . '/' . $id, json_encode($post));
            return '<p>Updated</p>';
        }

        $properties = $this->getProperties($subject, $action, $definition);
        $references = $this->getReferences($subject, $properties);
        $referenced = $this->getReferenced($subject, $properties);
        $primaryKey = $this->getPrimaryKey($subject, $properties);

        $data = $this->call('GET', $url . '/records/' . urlencode($subject) . '/' . $id);
        $html = '<h4>' . $subject . ': update</h4>';
        $html .= '<form method="post">';
        foreach ($data as $column => $value) {
            $html .= '<div class="form-group">';
            $html .= '<label for="' . $column . '">' . $column . '</label>';
            if ($references[$column]) {
                $html .= $this->selectSubject($url, $references[$column], $column, $value, $definition);
            } else {
                $readonly = $column == $primaryKey ? ' readonly' : '';
                $html .= '<input class="form-control" id="' . $column . '" name="' . $column . '" value="' . $value . '"' . $readonly . '/>';
            }
            $html .= '</div>';
        }
        $html .= '<button type="submit" class="btn btn-primary">Save</button>';
        $html .= '</form>';
        return $html;
    }

    public function executeDelete()
    {
        extract($this->settings);
        $subject = $this->getParameter(0);
        $action = $this->getParameter(1);
        $id = $this->getParameter(2);

        if ($method == 'POST') {
            $this->call('DELETE', $url . '/records/' . urlencode($subject) . '/' . $id);
            return '<p>Deleted</p>';
        }

        $properties = $this->getProperties($subject, $action, $definition);
        $references = $this->getReferences($subject, $properties);
        $referenced = $this->getReferenced($subject, $properties);
        $primaryKey = $this->getPrimaryKey($subject, $properties);

        $data = $this->call('GET', $url . '/records/' . urlencode($subject) . '/' . $id);
        $html = '<h4>Are you sure?</h4>';
        $html .= '<form method="post">';
        foreach ($data as $column => $field) {
            if ($column == $primaryKey) {
                $html .= '<input type="hidden" name="' . $column . '" value="' . $field . '"/>';
            }
        }
        $html .= '<button type="submit" class="btn btn-primary">Yes</button>';
        $href = $this->url($base, $subject, 'list');
        $html .= ' <a href="' . $href . '" class="btn btn-default">Cancel</a>';
        $html .= '</form>';
        return $html;
    }

    public function resolve($definition, $path)
    {
        while (null !== ($element = array_shift($path))) {
            //echo '"'.$element.'",';
            if (!isset($definition[$element])) {
                return false;
            }

            $definition = $definition[$element];
        }
        return $definition;
    }

    public function getProperties($subject, $action, $definition)
    {
        if (!$subject || !$definition) {
            return false;
        }
        if ($action == 'list') {
            $path = array('components', 'schemas', $action . '-' . $subject, 'properties', 'records', 'items', 'properties');
        } else {
            $path = array('components', 'schemas', $action . '-' . $subject, 'properties');
        }
        return $this->resolve($definition, $path);
    }

    public function getReferences($subject, $properties)
    {
        if (!$subject || !$properties) {
            return false;
        }

        $references = array();
        foreach ($properties as $field => $property) {
            $references[$field] = isset($property['x-references']) ? $property['x-references'] : false;
        }
        return $references;
    }

    public function getReferenced($subject, $properties)
    {
        if (!$subject || !$properties) {
            return false;
        }

        $referenced = array();
        foreach ($properties as $field => $property) {
            if (isset($property['x-referenced'])) {
                $referenced = array_merge($referenced, $property['x-referenced']);
            }
        }
        for ($i = 0; $i < count($referenced); $i++) {
            $referenced[$i] = explode('.', $referenced[$i]);
        }
        return $referenced;
    }

    public function getPrimaryKey($subject, $properties)
    {
        if (!$subject || !$properties) {
            return false;
        }

        foreach ($properties as $field => $property) {
            if (isset($property['x-primary-key'])) {
                return $field;
            }
        }
        return false;
    }

    public function __construct($config)
    {
        extract($config);

        // initialize
        $url = isset($url) ? $url : null;

        $base = isset($base) ? $base : null;
        $definition = isset($definition) ? $definition : null;
        $method = isset($method) ? $method : null;
        $request = isset($request) ? $request : null;
        $get = isset($get) ? $get : null;
        $post = isset($post) ? $post : null;

        // defaults
        if (!$definition) {
            $definition = isset($_SESSION['definition']) ? $_SESSION['definition'] : null;
            if (!$definition) {
                $definition = $this->call('GET', $url . '/openapi');
                $_SESSION['definition'] = $definition;
            }
        }
        if (!$method) {
            $method = $_SERVER['REQUEST_METHOD'];
        }
        if (!$request) {
            $request = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
            if (!$request) {
                $request = isset($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : '';
            }
        }
        if (!$get) {
            $get = $_GET;
        }
        if (!$post) {
            $post = $_POST;
        }

        $request = trim($request, '/');

        if (!$base) {
            $count = $request ? (-1 * strlen($request)) : strlen(urldecode($_SERVER['REQUEST_URI']));
            $base = rtrim(substr(urldecode($_SERVER['REQUEST_URI']), 0, $count), '/') . '/';
        }

        $this->settings = compact('url', 'base', 'definition', 'method', 'request', 'get', 'post');
    }

    protected function getParameter($position)
    {
        $request = $this->settings['request'];
        if (!$request) {
            return false;
        }
        $parameters = explode('/', $request);
        return isset($parameters[$position]) ? $parameters[$position] : '';
    }

    public function executeCommand()
    {
        $action = $this->getParameter(1);

        $html = $this->head();
        $html .= '<div class="row">';
        $html .= '<div class="col-md-3">';
        $html .= $this->menu();
        $html .= '</div>';

        $html .= '<div class="col-md-9">';
        switch ($action) {
            case '':
                $html .= $this->executeHome();
                break;
            case 'read':
                $html .= $this->executeView();
                break;
            case 'create':
                $html .= $this->executeAdd();
                break;
            case 'update':
                $html .= $this->executeEdit();
                break;
            case 'delete':
                $html .= $this->executeDelete();
                break;
            case 'list':
                $html .= $this->executeList();
                break;
        }
        $html .= '</div>';

        $html .= '</div>';
        $html .= $this->foot();
        return $html;
    }
}

//session_start();
//$ui = new PHP_CRUD_UI(array(
//    'url' => 'http://localhost:8000/api.php',
//));
//echo $ui->executeCommand();
