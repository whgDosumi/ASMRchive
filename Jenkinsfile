pipeline {
    agent any
    options {
        // For throttling other builds
        throttleJobProperty(
        categories: ['ASMRchive'],
        throttleEnabled: true,
        throttleOption: 'category'
        )
        // Only keep 3 builds
        buildDiscarder(logRotator(numToKeepStr: '3'))
    }
    parameters {
        // Determines whether we should skip the manual review step
        booleanParam(defaultValue: true, description: "Skip manual review?", name: "SKIP_REVIEW")
    }
    stages {
        stage ("Initialization") {
            steps {
                script {
                    // This is a placeholder, I will build this stage out more later
                    echo "Initializing"
                }
            }
        }
        stage ("Tidy Up") { // Cleans up environment to ensure we don't have artifacts from old builds
            steps {
                // We'll call the image created by this pipeline jenkins-asmrchive
                // Containers will be called the same
                echo "Removing existing testing containers"
                sh "podman ps -a -q -f ancestor=jenkins-asmrchive | xargs -I {} podman container rm -f {} || true" // Removes all containers that exist under the image
                echo "Removing existing image"
                sh "podman image rm jenkins-asmrchive || true"
            }   
        }
        stage ("Build Image") {
            steps {
                echo "Building image..."
                sh "podman --storage-opt ignore_chown_errors=true build -t jenkins-asmrchive ."
            }
        }
        stage ("Construct Container") {
            steps {
                echo "Constructing Container"
                sh """
                podman create \
                    -p 4445:80 \
                    --name jenkins-asmrchive \
                    jenkins-asmrchive
                """
                echo "Starting Container"
                sh "podman container start jenkins-asmrchive"
            }
        }
    }
}