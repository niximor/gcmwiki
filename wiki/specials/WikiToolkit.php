<?php

namespace specials;

require_once "mail/MailMessage.php";
require_once "specials/SpecialController.php";


class WikiToolkit extends SpecialController {
	public function login() {
		if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["username"]) && isset($_POST["password"])) {
			$user = \lib\CurrentUser::Verify($_POST["username"], $_POST["password"]);
			if ($user) {
				\lib\Session::Set("UserId", $user->getId());
				$this->template->redirect("/");
			} else {
				\view\Messages::Add("Invalid username or password.", \lib\Message::Error);
			}
		}

		$page = new \view\Template("wiki/login.php");
		$this->template->setChild($page);

		$this->template->addNavigation("System", NULL);
		$this->template->addNavigation("Log in", $this->template->getSelf());
		$this->template->setTitle("Log in");
	}

	public function logout() {
		if (!is_null(\lib\Session::Get("UserId"))) {
			\lib\Session::Set("UserId", 0);
		}
		$this->template->redirect("/");
	}

	public function register() {
		if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["username"]) && isset($_POST["password"]) && isset($_POST["email"])) {
			$u = new \models\User();
			$u->setName($_POST["username"]);
			$u->setPassword($_POST["password"]);
			$u->setEmail($_POST["email"]);

			$be = $this->getBackend();
			$be->storeUserInfo($u);

			\view\Messages::Add("Registration successfull.", \view\Message::Success);
			$this->template->redirect("/");
		}

		$page = new \view\Template("wiki/register.php");
		$this->template->setChild($page);

		$this->template->addNavigation("System", NULL);
		$this->template->addNavigation("Register", $this->template->getSelf());
		$this->template->setTitle("Register");
	}

	public function forgot_password() {
		if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["name"])) {
			$be = $this->getBackend();
			try {
				$user = $be->loadUserInfo($_POST["name"], array("name", "email"), array("password_token"));

				if ($user->email_verified) {
					$user->password_token = \lib\Session::str_rand(32);
					$be->storeUserInfo($user);

					$m = new \mail\MailMessage();
					$m->setTemplate("mail/forgot_password.tpl");
					$m->addRecipient($user->email);
					$m->bind("code", $user->password_token);
					$m->bind("username", $user->name);
					$m->Send();
					\view\Messages::Add("You should receive password recovery link through your email soon.", \view\Message::Success);
				} else {
					\view\Messages::Add("You don't have verified email address, password cannot be reset.", \view\Message::Error);
				}
			} catch (\drivers\EntryNotFoundException $e) {
				\view\Messages::Add("Given username or email does not exists.", \view\Message::Error);
			}

			$this->template->redirect("/wiki:forgot_password");
		} elseif (isset($_REQUEST["key"])) {
			$be = $this->getBackend();

			try {
				$user = $be->loadUserInfo($_REQUEST["key"], "password_token", array("salt"));
				if ($user) {
					// Two passwords must be set
					if (isset($_POST["password"]) && isset($_POST["password2"])) {

						// Passwords must match
						if ($_POST["password"] == $_POST["password2"]) {

							// Valid password - store and redirect to index.
							if (\models\User::validatePassword($_POST["password"])) {
								$user->password_token = "";
								$user->setPassword($_POST["password"]);
								$be->storeUserInfo($user);

								\view\Messages::Add("Password has been changed.", \view\Message::Success);
								$this->template->redirect("/wiki:login");

							// Invalid password - generate new token and redirect back to form.
							} else {
								$user->password_token = \lib\Session::str_rand(32);
								$be->storeUserInfo($user);
								$this->template->redirect($this->template->getSelf()."?key=".$user->password_token);
							}

						// Passwords did not match - generate new token and redirect
						} else {
							$user->password_token = \lib\Session::str_rand(32);
							$be->storeUserInfo($user);

							\view\Messages::Add("New passwords did not match.", \view\Message::Error);
							$this->template->redirect($this->template->getSelf()."?key=".$user->password_token);
						}

					// No two passwords, display page.
					} else {
						$user->password_token = \lib\Session::str_rand(32);
						$be->storeUserInfo($user);
					}
				}

				$child = new \view\Template("wiki/forgot_password_entry.php");
				$child->addVariable("User", $user);
				$this->template->setChild($child);
			} catch (\drivers\EntryNotFoundException $e) {
				\view\Messages::Add("Invalid password reset code.", \view\Message::Error);
			}
		} else {
			$child = new \view\Template("wiki/forgot_password.php");
			$this->template->setChild($child);
		}

		$this->template->addNavigation("System", NULL);
		$this->template->addNavigation("Forgotten password recovery", $this->template->getSelf());
		$this->template->setTitle("Forgotten password recovery");
	}

	public function user($name) {
		$be = $this->getBackend();
		$user = $be->loadUserInfo($name, "name");

		$child = new \view\Template("wiki/user.php");
		$child->addVariable("User", $user);

		if (\lib\CurrentUser::hasPriv("admin_user_privileges")) {
			$child->addVariable("AppliedPrivileges", $user->listAppliedPrivileges());
			$child->addVariable("UserGroups", $be->listGroups($user));
		}

		$this->template->setChild($child);

		$this->template->addNavigation("User profile", NULL);
		$this->template->addNavigation($user->getName(), $this->template->getSelf());
		$this->template->setTitle("User profile: ".$user->getName());
	}

	public function settings() {
		if (\lib\CurrentUser::isLoggedIn()) {
			if ($_SERVER["REQUEST_METHOD"] == "POST") {
				$u = \lib\CurrentUser::i();
				$u->setEmail($_POST["email"]);

				$be = $this->getBackend();
				$be->storeUserInfo($u);

				\view\Messages::Add("User settings were updated.", \view\Message::Success);

				$this->template->redirect($this->template->getSelf());
			}

			$child = new \view\Template("wiki/settings.php");
			$child->addVariable("User", \lib\CurrentUser::i());

			$this->template->setChild($child);
		} else {
			$child = new \view\Template("need_login.php");
			$this->template->setChild($child);
		}

		$this->template->addNavigation("System", NULL);
		$this->template->addNavigation("Settings", $this->template->getSelf());
		$this->template->setTitle("Settings");
	}

	public function change_password() {
		if (\lib\CurrentUser::isLoggedIn()) {
			if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["oldpassword"]) && isset($_POST["password"]) && isset($_POST["password2"])) {
				if (!\models\User::validatePassword($_POST["password"])) {
					// Error messages are set in User::validatePassword method.
				} elseif ($_POST["password"] != $_POST["password2"]) {
					\view\Messages::Add("New passwords did not match.", \view\Message::Error);
				} else {
					// Verify user's old password.
					$be = $this->getBackend();
					$u2 = $be->verifyUser(\lib\CurrentUser::i()->getName(), $_POST["oldpassword"]);
					if ($u2) {
						$u2->setPassword($_POST["password"]);
						$be->storeUserInfo($u2);
						\view\Messages::Add("Password has been changed.", \view\Message::Success);
					} else {
						\view\Messages::Add("Incorrect old password.", \view\Message::Error);
					}
				}
				$this->template->redirect($this->template->getSelf());
			} else {
				$child = new \view\Template("wiki/change_password.php");
				$child->addVariable("User", \lib\CurrentUser::i());
				$this->template->setChild($child);
			}
		} else {
			$child = new \view\Template("need_login.php");
			$this->template->setChild($child);
		}
	}

	public function user_groups($user) {
		$this->template->addNavigation("User profile", NULL);

		if (\lib\CurrentUser::hasPriv("admin_user_privileges")) {
			$be = $this->getBackend();
			try {
				$user = $be->loadUserInfo($user, "name");
				$this->template->addNavigation($user->getName(), "/wiki:user/".$user->getName());
				$this->template->addNavigation("Groups", $this->template->getSelf());
				$this->template->setTitle(sprintf("Groups %s is member of", $user->getName()));

				if ($_SERVER["REQUEST_METHOD"] == "POST") {
					// Add user to group
					if (isset($_POST["add"])) {
						if ($_POST["groupId"] == 0) {
							$group = new \models\Group;
							$group->setName($_POST["groupName"]);

							$be->storeGroupInfo($group);
							$be->addUserToGroup($user, $group);
						} else {
							$group = $be->loadGroupInfo($_POST["groupId"]);
							$be->addUserToGroup($user, $group);
						}

						\view\Messages::Add(sprintf("User has been added to group %s", $group->getName()), \view\Message::Success);
					} elseif (isset($_POST["remove"]) && is_array($_POST["remove"])) {
						foreach ($_POST["remove"] as $key=>$dummy) {
							$group = $be->loadGroupInfo($key);

							$be->removeUserFromGroup($user, $group);
							\view\Messages::Add(sprintf("User has been removed from group %s", $group->getName()), \view\Message::Success);
						}
					}

					$this->template->redirect($this->template->getSelf());
				}

				$child = new \view\Template("wiki/user_groups.php");
				$child->addVariable("User", $user);
				$child->addVariable("UserGroups", $be->listGroups($user));
				$child->addVariable("Groups", $be->listGroups());

				$this->template->setChild($child);
			} catch (\drivers\EntryNotFoundException $e) {
				$child = new \view\Template("wiki/not_found.php");
				$child->template->setChild($child);
			}
		} else {
			$child = new \view\Template("need_privileges.php");
			$this->template->setChild($child);
		}
	}

	public function groups() {
		if (\lib\CurrentUser::hasPriv("admin_groups")) {
			$be = $this->getBackend();

			if (isset($_REQUEST["listUsers"])) {
				$this->_group_listUsers($be, $be->loadGroupInfo($_REQUEST["listUsers"]));
			} elseif (isset($_REQUEST["modify"])) {
				$this->_group_modify($be, $be->loadGroupInfo($_REQUEST["modify"]));
			} elseif (isset($_REQUEST["remove"])) {
				$this->_group_remove($be, $be->loadGroupInfo($_REQUEST["remove"]));
			} elseif (isset($_REQUEST["privileges"])) {
				$this->_group_privileges($be, $be->loadGroupInfo($_REQUEST["privileges"]));
			} elseif (isset($_REQUEST["add"])) {
				$this->_group_add($be);
			} else {
				$this->_group_index($be);
			}
		}
	}

	protected function _group_index($be) {
		$child = new \view\Template("wiki/groups.php");
		$child->addVariable("Groups", $be->listGroups(NULL, array("userCount")));
		$this->template->setChild($child);

		$this->template->addNavigation("System", NULL);
		$this->template->addNavigation("Groups", $this->template->getSelf());
		$this->template->setTitle("Groups");
	}

	protected function _group_listUsers($be, \models\Group $group) {
		$this->template->addNavigation("System", NULL);
		$this->template->addNavigation("Groups", $this->template->getSelf());
		$this->template->addNavigation($group->getName(), NULL);
		$this->template->addNavigation("Users", $this->template->getSelf()."?listUsers=".$group->getId());
		$this->template->setTitle(sprintf("Users in group %s", $group->getName()));

		if (isset($_REQUEST["remove"])) {
			$u = new \models\User;
			$u->setId($_REQUEST["remove"]);

			$be->removeUserFromGroup($u, $group);
			\view\Messages::Add("User has been removed from group.", \view\Message::Success);
			$this->template->redirect($this->template->getSelf()."?listUsers=".$group->getId());
		} elseif (isset($_REQUEST["add"])) {
			if (!empty($_REQUEST["add"])) {
				$u = new \models\User;
				$u->setId($_REQUEST["add"]);

				$be->addUserToGroup($u, $group);
				\view\Messages::Add("User has been added to group.", \view\Message::Success);
			}
			$this->template->redirect($this->template->getSelf()."?listUsers=".$group->getId());
		}

		$child = new \view\Template("/wiki/groups/users.php");
		$child->addVariable("Group", $group);
		$child->addVariable("UsersInGroup", $be->listUsers($group));
		$child->addVariable("Users", $be->listUsers());
		$this->template->setChild($child);
	}

	protected function _group_modify($be, \models\Group $group) {
		$this->template->addNavigation("System", NULL);
		$this->template->addNavigation("Groups", $this->template->getSelf());
		$this->template->addNavigation($group->getName(), NULL);
		$this->template->addNavigation("Modify", $this->template->getSelf()."?modify=".$group->getId());
		$this->template->setTitle(sprintf("Modify group %s", $group->getName()));

		if ($_SERVER["REQUEST_METHOD"] == "POST") {
			$group->setName($_POST["name"]);
			$be->storeGroupInfo($group);

			\view\Messages::Add(sprintf("Group %s has been modified.", $group->getName()), \view\Message::Success);
			$this->template->redirect("/wiki:groups");
		} else {
			$child = new \view\Template("wiki/groups/modify.php");
			$child->addVariable("Group", $group);
			$this->template->setChild($child);
		}
	}

	protected function _group_remove($be, \models\Group $group) {
		$be->removeGroup($group);

		\view\Messages::Add(sprintf("Group %s has been removed.", $group->getName()), \view\Message::Success);

		$this->template->redirect("/wiki:groups");
	}

	protected function _group_privileges($be, \models\Group $group) {
		$this->template->addNavigation("System", NULL);
		$this->template->addNavigation("Groups", $this->template->getSelf());
		$this->template->addNavigation($group->getName(), NULL);
		$this->template->addNavigation("Privileges", $this->template->getSelf()."?privileges=".$group->getId());
		$this->template->setTitle(sprintf("Privileges of group %s", $group->getName()));

		if ($_SERVER["REQUEST_METHOD"] == "POST") {
			$set = array();

			foreach ($_POST["privilege"] as $key=>$value) {
				$priv = new \models\GroupSystemPrivilege;
				$priv->group_id = $group->getId();
				$priv->privilege_id = $key;
				if ($value == "1") {
					$priv->value = true;
				} elseif ($value == "0") {
					$priv->value = false;
				} elseif ($value == "-") {
					$priv->value = NULL;
				}

				$set[] = $priv;
			}

			$be->storeGroupPrivileges($set);
			\view\Messages::Add("Group privileges has been modified.", \view\Message::Success);

			$this->template->redirect($this->template->getSelf()."?privileges=".$group->getId());
		}
		$child = new \view\Template("wiki/groups/privileges.php");
		$child->addVariable("Group", $group);
		$child->addVariable("GroupPrivileges", $be->listGroupPrivileges($group));
		$this->template->setChild($child);
	}

	protected function _group_add($be) {
		if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["name"])) {
			$g = new \models\Group();
			$g->setName($_POST["name"]);

			$be->storeGroupInfo($g);
			\view\Messages::Add(sprintf("Group %s has been created.", $g->getName()), \view\Message::Success);
		}
		$this->template->redirect("/wiki:groups");
	}

	public function users() {
		if (\lib\CurrentUser::hasPriv("admin_users")) {
			$be = $this->getBackend();

			try {
				if (isset($_REQUEST["ban"])) {
					$this->_users_ban($be, $be->loadUserInfo($_REQUEST["ban"], "id", array("status_id")));
				} elseif (isset($_REQUEST["unban"])) {
					$this->_users_unban($be, $be->loadUserInfo($_REQUEST["unban"], "id", array("status_id")));
				} elseif (isset($_REQUEST["privileges"])) {
					$this->_users_privileges($be, $be->loadUserInfo($_REQUEST["privileges"]));
				} else {
					$this->_users_index($be);
				}
			} catch (\drivers\EntryNotFoundException $e) {
				$child = new \view\Template("wiki/not_found.php");
				$this->template->setChild($child);
			}
		} else {
			$child = new \view\Template("need_privileges.php");
			$this->template->setChild($child);
		}
	}

	protected function _users_ban($be, \models\User $user) {
		if ($user->status_id == \models\User::STATUS_LIVE) {
			$user->status_id = \models\User::STATUS_BANNED;
			$be->storeUserInfo($user);

			\view\Messages::Add(sprintf("User %s has been banned.", $user->getName()), \view\Message::Success);
		} else {
			\view\Messages::Add(sprintf("User %s cannot be banned.", $user->getName()), \view\Message::Error);
		}

		$this->template->redirect($this->template->getSelf());
	}

	protected function _users_unban($be, \models\User $user) {
		if ($user->status_id == User::STATUS_BANNED) {
			$user->status_id = User::STATUS_LIVE;
			$be->storeUserInfo($user);

			\view\Messages::Add(sprintf("User %s has been unbanned.", $user->getName()), \view\Message::Success);
		} else {
			\view\Messages::Add(sprintf("User %s is not banned.", $user->getName()), \view\Message::Error);
		}

		$this->template->redirect($this->template->getSelf());
	}

	protected function _users_privileges($be, \models\User $user) {
		$this->template->addNavigation("User profile", NULL);
		$this->template->addNavigation($user->getName(), "/wiki:user/".$user->getName());
		$this->template->addNavigation("Privileges", $this->template->getSelf()."?privileges=".$user->getId());
		$this->template->setTitle(sprintf("Privileges of %s", $user->getName()));

		if (\lib\CurrentUser::hasPriv("admin_user_privileges")) {
			if ($_SERVER["REQUEST_METHOD"] == "POST") {
				if (isset($_POST["privilege"]) && is_array($_POST["privilege"])) {
					$set = array();

					foreach ($_POST["privilege"] as $key => $val) {
						if ($val == "1") $val = true;
						elseif ($val == "0") $val = false;
						elseif ($val == "-1") $val = NULL;

						$priv = new \models\UserSystemPrivilege;
						$priv->user_id = $user->getId();
						$priv->privilege_id = (int)$key;
						$priv->value = $val;

						$set[] = $priv;
					}

					$be->storeUserPrivileges($set);
					\view\Messages::Add("Privileges has been modified.", \view\Message::Success);
				}

				$this->template->redirect($this->template->getSelf());
			}

			$child = new \view\Template("wiki/user_privileges.php");
			$child->addVariable("User", $user);
			$child->addVariable("UserPrivileges", $be->listUserPrivileges($user));
			$this->template->setChild($child);
		} else {
			$child = new \view\Template("need_privileges.php");
			$this->template->setChild($child);
		}
	}

	protected function _users_index($be) {
		$this->template->addNavigation("System", NULL);
		$this->template->addNavigation("Users", $this->template->getSelf());
		$this->template->setTitle("Users");

		$child = new \view\Template("wiki/users/index.php");
		$child->addVariable("Users", $be->listUsers(NULL, array("registered", "last_login", "status_id", "logged_in")));
		$this->template->setChild($child);
	}

	public function config() {
		if (\lib\CurrentUser::hasPriv("admin_superadmin")) {
			$be = $this->getBackend();

			$SystemVariables = $be->listSystemVariables();
			$DefaultPrivileges = $be->listDefaultPrivileges();

			if ($_SERVER["REQUEST_METHOD"] == "POST") {
				// Store variables
				$update = array();

				if (!isset($_POST["variable"]) || !is_array($_POST["variable"])) {
					$_POST["variable"] = array();
				}

				foreach ($SystemVariables as $var) {
					if (isset($_POST["variable"][$var->name])) {
						$var->value = $_POST["variable"][$var->name];
						$update[] = $var;
					}
				}

				$be->setSystemVariables($update);

				// Store privileges
				$update = array();

				if (!isset($_POST["privilege"]) || !is_array($_POST["privilege"])) {
					$_POST["privilege"] = array();
				}

				foreach ($DefaultPrivileges as $priv) {
					if (isset($_POST["privilege"][$priv->id])) {
						$val = $_POST["privilege"][$priv->id];

						if ($val == "0") $val = false;
						elseif ($val == "1") $val = true;

						$priv->value = $val;
						$update[] = $priv;
					}
				}

				$be->storeDefaultPrivileges($update);

				\view\Messages::Add("Settings has been updated.", \view\Message::Success);

				$this->template->redirect($this->template->getSelf());
			}

			if (isset($_REQUEST["listPrivilege"])) {
				$found = false;
				foreach ($DefaultPrivileges as $priv) {
					if ($priv->getId() == $_REQUEST["listPrivilege"]) {
						$child = new \view\Template("wiki/list_privilege.php");

						list($default, $users) = $be->listUsersOfPrivilege($priv);

						$child->addVariable("Privilege", $priv);
						$child->addVariable("DefaultValue", $default);
						$child->addVariable("Users", $users);

						$this->template->setChild($child);
						$found = true;
						break;
					}
				}

				if (!$found) {
					$child = new \view\Template("wiki/not_found.php");
					$this->template->setChild($child);
				}
			} else {
				$child = new \view\Template("wiki/config.php");
				$child->addVariable("Privileges", $DefaultPrivileges);
				$child->addVariable("Config", $SystemVariables);

				$this->template->setChild($child);
			}
		} else {
			$child = new \view\Template("need_privileges.php");
			$this->template->setChild($child);
		}
	}
}

\Config::registerSpecial("wiki", "\\specials\\WikiToolkit");

