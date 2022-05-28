# PetitNote 全体ログ修復ツール
## 個別スレッドのログファイルから全体ログを修復します。

### Petit Noteの全体ログを修復します。
### 全体ログとは?
スレッドの表示順を管理しているログファイルです。
`log/`ディレクトリの、`alllog.log`です。  

### 修復可能なケース
- ログ飛びなどで`alllog.log`が空行や不明な文字列に化けてしまった時。
- `alllog.log`が存在しない時。
- `alllog.log`が初期化されてしまった時。
### 使い方
- ダウンロードは、[お絵かき掲示板 Petit Note のプラグイン](https://github.com/satopian/PetitNote_plugin)のページの緑の｢Code｣ボタンから。  
- `index.php`と同じディレクトリに配置します。
- `recoverlog.php`をブラウザで呼び出します。
- `new_alllog.log`というファイルに新しく作り直された全体ログが出力されます。

### 生成されたログファイルのファイル名を変更

- `nwe_alllog.log`というファイル名を、`alllog.log`に書き換えます。
- すでに`alllog.log`が存在している場合は、`alllog.log`を別のファイル名に変更して入れ替えます。
- 掲示板が正常に表示されてる事を確認します。
