<?php
/*
 * vim:set softtabstop=4 shiftwidth=4 expandtab:
 *
 * LICENSE: GNU Affero General Public License, version 3 (AGPL-3.0-or-later)
 * Copyright 2001 - 2020 Ampache.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=0);

namespace Ampache\Module\Application\Admin\User;

use Ampache\Config\ConfigContainerInterface;
use Ampache\Module\System\Core;
use Ampache\Module\Util\UiInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ShowGenerateRsstokenAction extends AbstractUserAction
{
    public const REQUEST_KEY = 'show_generate_rsstoken';

    private UiInterface $ui;

    private ConfigContainerInterface $configContainer;

    public function __construct(
        UiInterface $ui,
        ConfigContainerInterface $configContainer
    ) {
        $this->ui              = $ui;
        $this->configContainer = $configContainer;
    }

    protected function handle(ServerRequestInterface $request): ?ResponseInterface
    {
        $this->ui->showHeader();

        $user_id = Core::get_request('user_id');

        $this->ui->showConfirmation(
            T_('Are You Sure?'),
            T_('This will replace your existing RSS token. Feeds with the old token might not work properly'),
            sprintf(
                '%s/admin/users.php?action=generate_rsstoken&user_id=%s',
                $this->configContainer->getWebPath(),
                scrub_out($user_id)
            ),
            1,
            'generate_rsstoken');

        $this->ui->showQueryStats();
        $this->ui->showFooter();

        return null;
    }
}
