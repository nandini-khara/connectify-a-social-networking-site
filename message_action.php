<?php
/**
 * message_action.php
 * Handles: delete_me, undo_delete_me, delete_everyone, undo_delete_everyone,
 *          react, edit, clear_chat
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) { echo json_encode(['status'=>'error','msg'=>'Not logged in']); exit; }

require_once 'connect.php';
$me     = (int)$_SESSION['user_id'];
$action = trim($_POST['action'] ?? '');
$msgId  = (int)($_POST['message_id'] ?? 0);

function fail($m){ echo json_encode(['status'=>'error','msg'=>$m]); exit; }
function ok($extra=[]){ echo json_encode(array_merge(['status'=>'success'],$extra)); exit; }

function getMsg($con,$msgId,$me,$ownerOnly=false){
    $s=$con->prepare("SELECT * FROM messages WHERE message_id=? LIMIT 1");
    $s->bind_param('i',$msgId);$s->execute();
    $row=$s->get_result()->fetch_assoc();$s->close();
    if(!$row) fail('Message not found');
    if($ownerOnly&&(int)$row['sender_id']!==$me) fail('Not your message');
    return $row;
}

switch($action){

    case 'delete_me':
        $row=getMsg($con,$msgId,$me,false);
        if((int)$row['sender_id']!==$me&&(int)$row['receiver_id']!==$me) fail('Not your message');
        $u=$con->prepare("UPDATE messages SET deleted_for_me=1,deleted_for_me_by=?,deleted_for_me_at=NOW() WHERE message_id=?");
        $u->bind_param('ii',$me,$msgId);$u->execute(); ok();

    case 'undo_delete_me':
        $row=getMsg($con,$msgId,$me,false);
        if(!$row['deleted_for_me']||(int)$row['deleted_for_me_by']!==$me) fail('Not deleted by you');
        if((time()-strtotime($row['deleted_for_me_at']))/60>5) fail('5-minute undo window expired');
        $u=$con->prepare("UPDATE messages SET deleted_for_me=0,deleted_for_me_by=NULL,deleted_for_me_at=NULL WHERE message_id=?");
        $u->bind_param('i',$msgId);$u->execute(); ok();

    case 'delete_everyone':
        $row=getMsg($con,$msgId,$me,true);
        if((time()-strtotime($row['created_at']))/60>10) fail('10-minute window expired');
        $u=$con->prepare("UPDATE messages SET deleted_everyone=1,deleted_everyone_at=NOW() WHERE message_id=?");
        $u->bind_param('i',$msgId);$u->execute(); ok();

    case 'undo_delete_everyone':
        $row=getMsg($con,$msgId,$me,true);
        if(!$row['deleted_everyone']) fail('Not deleted for everyone');
        if((time()-strtotime($row['deleted_everyone_at']))/60>5) fail('5-minute undo window expired');
        $u=$con->prepare("UPDATE messages SET deleted_everyone=0,deleted_everyone_at=NULL WHERE message_id=?");
        $u->bind_param('i',$msgId);$u->execute(); ok();

    case 'react':
        $emoji=trim($_POST['emoji']??'');
        if(!$emoji) fail('No emoji');
        $chk=$con->prepare("SELECT id FROM message_reactions WHERE message_id=? AND user_id=? AND emoji=? LIMIT 1");
        $chk->bind_param('iis',$msgId,$me,$emoji);$chk->execute();
        if($chk->get_result()->num_rows>0){
            $d=$con->prepare("DELETE FROM message_reactions WHERE message_id=? AND user_id=?");
            $d->bind_param('ii',$msgId,$me);$d->execute();
            ok(['toggled'=>'off']);
        }
        $ins=$con->prepare("INSERT INTO message_reactions (message_id,user_id,emoji) VALUES (?,?,?) ON DUPLICATE KEY UPDATE emoji=VALUES(emoji),created_at=NOW()");
        $ins->bind_param('iis',$msgId,$me,$emoji);$ins->execute();
        ok(['toggled'=>'on']);

    case 'edit':
        $newText=trim($_POST['new_text']??'');
        if($newText==='') fail('Empty text');
        $row=getMsg($con,$msgId,$me,true);
        if((time()-strtotime($row['created_at']))/60>10) fail('10-minute edit window expired');
        if($row['deleted_everyone']||$row['deleted_for_me']) fail('Message deleted');
        require_once 'chat_crypto.php';
        $other=($row['sender_id']==$me)?$row['receiver_id']:$row['sender_id'];
        $aesKey=getOrCreateConversationKey($con,$me,$other);
        $encNew=encryptMessage($newText,$aesKey);
        $u=$con->prepare("UPDATE messages SET message_text=?,message_enc=?,is_edited=1,edited_at=NOW(),original_message=COALESCE(original_message,message_text) WHERE message_id=?");
        $u->bind_param('ssi',$newText,$encNew,$msgId);$u->execute();
        ok(['new_text'=>htmlspecialchars($newText)]);

    /* ── CLEAR CHAT ── */
    case 'clear_chat':
        $otherId=(int)($_POST['other_id']??0);
        if(!$otherId) fail('Missing other_id');
        // Upsert — updates cleared_at if already exists
        $ins=$con->prepare("
            INSERT INTO chat_clears (clearer_id, other_id, cleared_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE cleared_at = NOW()
        ");
        $ins->bind_param('ii',$me,$otherId);
        $ins->execute();
        ok();

    default:
        fail('Unknown action');
}