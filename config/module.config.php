<?php
namespace TypesenseSync;

use TypesenseSync\Controller;

return [
    'controllers' => [
        'invokables' => [
            'TypesenseSync\Controller\Index' => 'TypesenseSync\Controller\IndexController',
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'typesense-sync' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/typesense-sync',
                            'defaults' => [
                                '__NAMESPACE__' => 'TypesenseSync\Controller',
                                'controller' => 'Index',
                                'action' => 'index',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'trigger' => [
                                'type' => 'Literal',
                                'options' => [
                                    'route' => '/trigger',
                                    'defaults' => [
                                        'action' => 'trigger',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Typesense Sync',
                'route' => 'admin/typesense-sync',
                'resource' => 'TypesenseSync\Controller\Index',
            ],
        ],
    ],
    'view_manager' => [
        // dirname(__DIR__), not OMEKA_PATH . '/modules/TypesenseSync', so the
        // module still resolves its templates if the directory is ever renamed.
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
];
