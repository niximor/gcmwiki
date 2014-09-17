<h1>System configuration</h1>

<form action="<?php echo $this->url($this->getSelf()); ?>" method="post">
    <table>
        <tbody>
<?php
    foreach ($Config as $var) {
?>
            <tr>
                <td><label for="variable_<?php echo $var->id; ?>"><?php echo htmlspecialchars($var->name); ?></label></td>
                <td><input type="text" name="variable[<?php echo $var->name; ?>]" value="<?php echo htmlspecialchars($var->value); ?>" id="variable_<?php echo $var->id; ?>" /></td>
            </tr>
<?php
    }
?>
        </tbody>
    </table>

    <h2>Default privileges</h2>
    <table>
        <tbody>
<?php
    foreach ($Privileges as $priv) {
?>
            <tr>
                <td><label for="privilege_<?php echo $priv->id; ?>"><?php echo htmlspecialchars($priv->name); ?></label></td>
                <td>
                    <select name="privilege[<?php echo $priv->id; ?>]" id="privilege_<?php echo $priv->id; ?>">
                        <option value="1"<?php if ($priv->value) echo " selected=\"selected\""; ?>>Yes</option>
                        <option value="0"<?php if (!$priv->value) echo " selected=\"selected\""; ?>>No</option>
                    </select>
                </td>
                <td><a href="<?php echo $this->url($this->getSelf())."?listPrivilege=".$priv->id; ?>">Who has this?</a></td>
            </tr>
<?php
    }
?>
        </tbody>
    </table>

    <div class="buttons">
        <input type="submit" value="Save" />
    </div>
</form>