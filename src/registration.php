<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_CompareGraphQl
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'ScandiPWA_CompareGraphQl',
    __DIR__
);
