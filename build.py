import subprocess
import shutil
import os

def run_phpdoc():
    try:
        # phpdoc is run from Docker, so this whole command is a bit complex
        # see the readme: it has an alias to create phpdoc, then shows
        # how to call it with its arguments

        docker_command = [
            'docker', 'run', '--rm', '-v', f"{os.getcwd()}:/data", 'phpdoc/phpdoc:3',
            '--directory=src',
            '--target=docs',
            '--template=phpdocumentor-markdown-customised/themes/markdown',
            '--title=Respectify PHP Library',
            '-c', 'phpdoc.xml'
        ]
        
        # Run the Docker command
        subprocess.run(docker_command, check=True)
        print("phpDocumentor ran successfully.")
    except subprocess.CalledProcessError as e:
        print(f"Error running phpDocumentor: {e}")

def copy_docs():
    src_folder = 'docs'
    dest_folder = 'docs_ex/php'

    try:
        # Ensure the destination directory exists
        os.makedirs(dest_folder, exist_ok=True)
        
        # Copy the contents of the docs folder to the destination folder
        for item in os.listdir(src_folder):
            s = os.path.join(src_folder, item)
            d = os.path.join(dest_folder, item)
            if os.path.isdir(s):
                shutil.copytree(s, d, dirs_exist_ok=True)
            else:
                shutil.copy2(s, d)
        print("Docs copied successfully.")
    except Exception as e:
        print(f"Error copying docs: {e}")

if __name__ == "__main__":
    run_phpdoc()
    copy_docs()