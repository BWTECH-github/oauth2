<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

namespace OCA\OAuth2\Controller;

use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAvatarManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use RuntimeException;

class OpenIdConnectController extends ApiController {
	public function __construct(
		$AppName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly IURLGenerator $urlGenerator,
		private readonly IAvatarManager $avatarManager
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * Implements OpenID Connect UserInfo endpoint
	 *
	 * @see https://connect2id.com/products/server/docs/api/userinfo
	 *
	 * @return JSONResponse The claims as JSON Object.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @CORS
	 * @throws \Exception
	 */
	public function userInfo(): JSONResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			// should never happen
			throw new RuntimeException('Not logged in');
		}

		$data = [
			'sub' => $user->getUID()
		];
		$avatarUrl = $this->getAvatarUrl($user);
		if ($avatarUrl !== null) {
			$data['picture'] = $avatarUrl;
		}
		if ($user->getDisplayName() !== null) {
			$data['name'] = $user->getDisplayName();
		}
		if ($user->getEMailAddress() !== null) {
			$data['email'] = $user->getEMailAddress();
		}
		return new JSONResponse($data);
	}

	/**
	 * @throws \Exception
	 * @throws \OCP\Files\NotFoundException
	 */
	public function getAvatarUrl(IUser $user): ?string {
		$avatar = $this->avatarManager->getAvatar($user->getUID());
		if (!$avatar->exists()) {
			return null;
		}

		$avatarUrl = $this->urlGenerator->linkTo('', 'remote.php');
		$avatarUrl .= "/dav/avatars/{$user->getUID()}/96.jpeg";

		return $this->urlGenerator->getAbsoluteURL($avatarUrl);
	}
}
