<?php

$config = array(
    'forumId' => 2,
    'forumMappings' => array(),
    'resourceCategoryId' => 1,
    'resourceCategoryMappings' => array(),
    'resourceIds' => array(),
    'threadIds' => array(),
    'userId' => 1,
    'userMappings' => array(),
);
$models = array();
$preOutput = array();
$output = array();

require(dirname(__FILE__) . '/bootstrap.php');

function main()
{
    global $argv, $config, $models, $preOutput, $output;

    foreach (array_slice($argv, 1) as $arg) {
        $result = array();
        parse_str($arg, $result);

        if (!empty($result)) {
            foreach ($result as $key => $value) {
                if (!isset($config[$key])) {
                    continue;
                }

                if (is_int($config[$key])) {
                    $config[$key] = intval($value);
                } elseif (is_string($config[$key])) {
                    $config[$key] = strval($value);
                } elseif (is_array($config[$key])) {
                    if (is_string($value)) {
                        $config[$key] = array_map('intval', preg_split('#[^\d]#', $value, -1, PREG_SPLIT_NO_EMPTY));
                    } elseif (is_array($value)) {
                        $config[$key] = array_map('intval', $value);
                    }
                }
            }
        }
    }

    $jobFound = false;
    $jobKeys = array('resourceIds', 'threadIds');
    foreach ($jobKeys as $key) {
        $jobFound = $jobFound || !empty($config[$key]);
    }
    if (!$jobFound) {
        echo("Please config job to export...\n");
        var_export($config);
        exit(1);
    }

    setupVisitor();

    foreach ($config['resourceIds'] as $i => $resourceId) {
        $output[] = sprintf('// resourceIds[%d] = %d', $i, $resourceId);
        exportResource($resourceId);
    }

    foreach ($config['threadIds'] as $i => $threadId) {
        $output[] = sprintf('// threadIds[%d] = %d', $i, $threadId);
        exportThread($threadId);
    }

    echo("<?php\n\n");
    echo('$startTime = microtime(true);
$fileDir = dirname(__FILE__);

require($fileDir . \'/library/XenForo/Autoloader.php\');
XenForo_Autoloader::getInstance()->setupAutoloader($fileDir . \'/library\');

XenForo_Application::initialize($fileDir . \'/library\', $fileDir);
XenForo_Application::set(\'page_start_time\', $startTime);');
    echo("\n\n");

    if (!empty($models)) {
        ksort($models);
        echo("// Models\n");
        echo(implode("\n", $models));
        echo("\n\n");
    }
    if (!empty($preOutput)) {
        ksort($preOutput);
        echo("// Pre-output\n");
        echo(implode("\n", $preOutput));
        echo("\n\n");
    }
    echo("// Output\n");
    echo(implode("\n", $output));
    echo("\n\n");
}

function exportResource($resourceId)
{
    global $output;

    /** @var XenResource_Model_Resource $resourceModel */
    $resourceModel = getModelFromCache('XenResource_Model_Resource');
    $resourceDwClass = 'XenResource_DataWriter_Resource';

    $resource = $resourceModel->getResourceById($resourceId, array(
        'join' => XenResource_Model_Resource::FETCH_DESCRIPTION
            | XenResource_Model_Resource::FETCH_VERSION,
    ));
    if (empty($resource)) {
        return false;
    }

    /** @var XenResource_Model_Category $categoryModel */
    $categoryModel = getModelFromCache('XenResource_Model_Category');
    $category = $categoryModel->getCategoryById($resource['resource_category_id']);
    if (empty($category)) {
        return false;
    }

    $resource = $resourceModel->prepareResource($resource, $category);
    $resource = $resourceModel->prepareResourceCustomFields($resource, $category);

    $output[] = sprintf('/** @var %s $resourceDw */', $resourceDwClass);
    $output[] = sprintf('$resourceDw = XenForo_DataWriter::create(%s);', varExport($resourceDwClass));

    $userVar = preloadUser($resource['user_id']);
    $output[] = sprintf('$resourceDw->set(\'user_id\', %s[\'user_id\']);', $userVar);
    $output[] = sprintf('$resourceDw->set(\'username\', %s[\'username\']);', $userVar);

    $categoryVar = preloadResourceCategory($resource['resource_category_id']);
    $output[] = sprintf('$resourceDw->set(\'resource_category_id\', %s[\'resource_category_id\']);', $categoryVar);

    foreach (array(
                 'title',
                 'tag_line',
                 'external_url',
                 'alt_support_url',
                 // TODO: 'prefix_id',
             ) as $key) {
        $output[] = sprintf('$resourceDw->set(\'%s\', %s);', $key, varExport($resource[$key]));
    }

    if (!empty($resource['customFields'])) {
        foreach ($resource['customFields'] as $customFieldId => $value) {
            if (empty($value)) {
                continue;
            }

            preloadResourceField($customFieldId);
        }
        $output[] = sprintf('$resourceCustomFields = %s;', varExport($resource['customFields']));
        $output[] = '$resourceDw->setCustomFields($resourceCustomFields);';
    }

    $output[] = '$resourceDescriptionDw = $resourceDw->getDescriptionDw();';
    $output[] = sprintf('$message = %s;', varExport($resource['description']));
    $output[] = '$resourceDescriptionDw->set(\'message\', $message);';

    $output[] = '$resourceVersionDw = $resourceDw->getVersionDw();';
    if (!empty($resource['download_url'])) {
        $output[] = sprintf('$resourceVersionDw->set(\'download_url\', %s);', varExport($resource['download_url']));
    } elseif (!empty($resource['is_fileless'])) {
        $output[] = '$resourceDw->set(\'is_fileless\', 1);';
        $output[] = '$resourceVersionDw->setOption(XenResource_DataWriter_Version::OPTION_IS_FILELESS, true);';

        if (!empty($resource['external_purchase_url'])) {
            foreach (array(
                         'price',
                         'currency',
                         'external_purchase_url',
                     ) as $key) {
                $output[] = sprintf('$resourceDw->set(\'%s\', %s);', $key, varExport($resource[$key]));
            }
        }
    }

    $output[] = sprintf('$resourceVersionDw->set(\'version_string\', %s);', varExport($resource['version_string']));

    if (!empty($resource['tagsList'])) {
        outputModel('XenForo_Model_Tag', '$tagModel');
        $output[] = '$tagger = $tagModel->getTagger(\'resource\');';
        $hasTagger = true;
        $output[] = sprintf('$tagger->setPermissionsFromContext(%s);', $categoryVar);

        $tagTexts = array();
        foreach ($resource['tagsList'] as $tag) {
            $tagTexts[] = $tag['tag'];
        }
        $output[] = sprintf('$tagTexts = %s;', varExport($tagTexts));
        $output[] = '$tagger->setTags($tagTexts);';
        $output[] = '$resourceDw->mergeErrors($tagger->getErrors());';
    }

    $output[] = '$resourceDw->save();';
    $output[] = '$resource = $resourceDw->getMergedData();';
    $output[] = 'echo(sprintf("Created resource #%d\n", $resource[\'resource_id\']));';

    if (!empty($hasTagger)) {
        $output[] = '$tagger->setContent($resource[\'resource_id\'], true)->save();';
    }

    return true;
}

function exportThread($threadId)
{
    global $output;

    /** @var XenForo_Model_Thread $threadModel */
    $threadModel = getModelFromCache('XenForo_Model_Thread');
    $threadDwClass = 'XenForo_DataWriter_Discussion_Thread';

    $thread = $threadModel->getThreadById($threadId, array(
        'join' => XenForo_Model_Thread::FETCH_FIRSTPOST,
    ));
    if (empty($thread)) {
        return false;
    }

    /** @var XenForo_Model_Forum $forumModel */
    $forumModel = getModelFromCache('XenForo_Model_Forum');
    $forum = $forumModel->getForumById($thread['node_id']);
    if (empty($forum)) {
        return false;
    }

    $thread = $threadModel->prepareThread($thread, $forum);

    $output[] = sprintf('/** @var %s $threadDw */', $threadDwClass);
    $output[] = sprintf('$threadDw = XenForo_DataWriter::create(%s);', varExport($threadDwClass));

    $userVar = preloadUser($thread['user_id']);
    $output[] = sprintf('$threadDw->set(\'user_id\', %s[\'user_id\']);', $userVar);
    $output[] = sprintf('$threadDw->set(\'username\', %s[\'username\']);', $userVar);

    $forumVar = preloadForum($thread['node_id']);
    $output[] = sprintf('$threadDw->set(\'node_id\', %s[\'node_id\']);', $forumVar);

    $output[] = sprintf('$threadDw->set(\'title\', %s);', varExport($thread['title']));

    $output[] = '$threadPostDw = $threadDw->getFirstMessageDw();';
    $message = $thread['message'];
    $message = convertAttachToImg($message);
    $output[] = sprintf('$threadPostDw->set(\'message\', %s);', varExport($message));
    $output[] = sprintf('$threadPostDw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, '
        . '%s);', $forumVar);

    $output[] = sprintf('$threadDw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, %s);', $forumVar);

    if (!empty($thread['tagsList'])) {
        outputModel('XenForo_Model_Tag', '$tagModel');
        $output[] = '$tagger = $tagModel->getTagger(\'thread\');';
        $hasTagger = true;
        $output[] = sprintf('$tagger->setPermissionsFromContext(%s);', $forumVar);

        $tagTexts = array();
        foreach ($thread['tagsList'] as $tag) {
            $tagTexts[] = $tag['tag'];
        }
        $output[] = sprintf('$tagTexts = %s;', varExport($tagTexts));
        $output[] = '$tagger->setTags($tagTexts);';
        $output[] = '$threadDw->mergeErrors($tagger->getErrors());';
    }

    $output[] = '$threadDw->save();';
    $output[] = '$thread = $threadDw->getMergedData();';
    $output[] = 'echo(sprintf("Created thread #%d\n", $thread[\'thread_id\']));';

    if (!empty($hasTagger)) {
        $output[] = '$tagger->setContent($thread[\'thread_id\'], true)->save();';
    }

    return true;
}

function preloadForum($nodeId)
{
    global $config, $preOutput;

    outputModel('XenForo_Model_Forum', '$forumModel');

    $allVar = '$forums';
    $preOutput['$forum'] = sprintf('%s = $forumModel->getForums();', $allVar);

    $mappedNodeId = $config['forumId'];
    if (isset($config['forumMappings'][$nodeId])) {
        $mappedNodeId = $config['forumMappings'][$nodeId];
    }
    $var = sprintf('$forum%d', $mappedNodeId);
    $preOutput[$var] = sprintf('%s = %s[%d];', $var, $allVar, $mappedNodeId);

    return $var;
}

function preloadResourceCategories()
{
    global $preOutput;

    outputModel('XenResource_Model_Category', '$resourceCategoryModel');

    $var = '$resourceCategories';
    $preOutput[$var] = sprintf('%s = $resourceCategoryModel->getAllCategories();', $var);

    return $var;
}

function preloadResourceCategory($categoryId)
{
    global $config, $preOutput;

    $mappedCategoryId = $config['resourceCategoryId'];
    if (isset($config['resourceCategoryMappings'][$categoryId])) {
        $mappedCategoryId = $config['resourceCategoryMappings'][$categoryId];
    }

    $allVar = preloadResourceCategories();

    $var = sprintf('$resourceCategory%d', $mappedCategoryId);
    $preOutput[$var] = sprintf('%s = %s[%d];', $var, $allVar, $mappedCategoryId);

    return $var;
}

function preloadResourceField($fieldId)
{
    global $preOutput;

    /** @var XenResource_Model_ResourceField $resourceFieldModel */
    $resourceFieldModel = getModelFromCache('XenResource_Model_ResourceField');
    outputModel('XenResource_Model_ResourceField', '$resourceFieldModel');

    $field = $resourceFieldModel->getResourceFieldById($fieldId);
    $field = $resourceFieldModel->prepareResourceField($field, true);

    $allVar = '$resourceFields';
    $preOutput[$allVar] = sprintf('%s = $resourceFieldModel->getResourceFields();', $allVar);

    $var = sprintf('%s[%s]', $allVar, varExport($fieldId));
    $fieldOutput = array();
    $fieldOutput[] = sprintf('if (!isset(%s)) {', $var);
    $fieldOutput[] = '  /** @var XenResource_DataWriter_ResourceField $resourceFieldDw */';
    $fieldOutput[] = '  $resourceFieldDw = XenForo_DataWriter::create(\'XenResource_DataWriter_ResourceField\');';
    $fieldOutput[] = sprintf('  $resourceFieldDw->set(\'field_id\', %s);', varExport($fieldId));

    foreach (array(
                 'field_type',
                 'match_type',
                 'match_regex',
                 'match_callback_class',
                 'match_callback_method',
                 'max_length',
                 'required',
                 'display_template',
             ) as $key) {
        if (empty($field[$key])) {
            continue;
        }

        $fieldOutput[] = sprintf('  $resourceFieldDw->set(\'%s\', %s);', $key, varExport($field[$key]));
    }

    $fieldOutput[] = sprintf('  $resourceFieldDw->setExtraData(XenResource_DataWriter_ResourceField::DATA_CATEGORY_IDS,'
        . ' array_keys(%s));', preloadResourceCategories());

    $fieldOutput[] = sprintf('  $resourceFieldDw->setExtraData(XenResource_DataWriter_ResourceField::DATA_TITLE, '
        . '%s);', varExport(strval($field['title'])));
    $fieldOutput[] = sprintf('  $resourceFieldDw->setExtraData(XenResource_DataWriter_ResourceField::DATA_DESCRIPTION, '
        . '%s);', varExport(strval($field['description'])));

    if (!empty($field['fieldChoices'])) {
        $fieldChoices = array_map('strval', $field['fieldChoices']);
        $fieldOutput[] = sprintf('  $fieldChoices = %s;', varExport($fieldChoices));
        $fieldOutput[] = '  $resourceFieldDw->setFieldChoices($fieldChoices);';
    }

    $fieldOutput[] = '  $resourceFieldDw->save();';
    $fieldOutput[] = sprintf('  %s = $resourceFieldDw->getMergedData();', $var);
    $fieldOutput[] = sprintf('  echo(sprintf("Created resource field #%%s\n", %s[\'field_id\']));', $var);

    $fieldOutput[] = '}';

    $preOutput[$var] = implode("\n", $fieldOutput);
}

function preloadUser($userId)
{
    global $config, $preOutput;

    $mappedUserId = $config['userId'];
    if (isset($config['userMappings'][$userId])) {
        $mappedUserId = $config['userMappings'][$userId];
    }

    outputModel('XenForo_Model_User', '$userModel');

    $var = sprintf('$user%d', $mappedUserId);
    $preOutput[$var] = sprintf('%s = $userModel->getUserById(%d);', $var, $mappedUserId);

    return $var;
}

function setupVisitor()
{
    global $output;

    $var = preloadUser(0);
    $output[] = sprintf('XenForo_Visitor::setup(%s[\'user_id\']);', $var);
    $output[] = '';
}

function outputModel($class, $var)
{
    global $models;

    $models[$var] = implode("\n", array(
        sprintf('/** @var %s %s */', $class, $var),
        sprintf('%s = XenForo_Model::create(%s);', $var, varExport($class))
    ));
}

function convertAttachToImg($bbCode)
{
    $offset = 0;
    while (true) {
        if (!preg_match('#\[ATTACH[^\]]*\](?<id>\d+)\[/ATTACH\]#i', $bbCode, $matches, PREG_OFFSET_CAPTURE, $offset)) {
            return $bbCode;
        }

        $attachmentId = intval($matches['id'][0]);
        $offset = $matches[0][1] + strlen($matches[0][0]);

        /** @var XenForo_Model_Attachment $attachmentModel */
        $attachmentModel = getModelFromCache('XenForo_Model_Attachment');
        $attachment = $attachmentModel->getAttachmentById($attachmentId);
        if (empty($attachment)) {
            continue;
        }

        $url = XenForo_Link::buildPublicLink('canonical:attachments', $attachment);
        $replacement = sprintf('[IMG]%s[/IMG]', $url);
        $bbCode = substr_replace($bbCode, $replacement, $matches[0][1], strlen($matches[0][0]));
    }

    return $bbCode;
}

function getModelFromCache($class)
{
    static $models = array();

    if (!isset($models[$class])) {
        $models[$class] = XenForo_Model::create($class);
    }

    return $models[$class];
}

function varExport($var)
{
    return var_export($var, true);
}

main();
