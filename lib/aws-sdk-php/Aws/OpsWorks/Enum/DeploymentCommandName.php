<?php
/**
 * Copyright 2010-2013 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */

namespace Aws\OpsWorks\Enum;

use Aws\Common\Enum;

/**
 * Contains enumerable DeploymentCommandName values
 */
class DeploymentCommandName extends Enum
{
    const INSTALL_DEPENDENCIES = 'install_dependencies';
    const UPDATE_DEPENDENCIES = 'update_dependencies';
    const UPDATE_CUSTOM_COOKBOOKS = 'update_custom_cookbooks';
    const EXECUTE_RECIPES = 'execute_recipes';
    const DEPLOY = 'deploy';
    const ROLLBACK = 'rollback';
    const START = 'start';
    const STOP = 'stop';
    const RESTART = 'restart';
    const UNDEPLOY = 'undeploy';
}
