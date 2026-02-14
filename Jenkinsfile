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
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }
    parameters {
        // Determines whether we should skip the manual review step
        booleanParam(defaultValue: true, description: "Skip manual review?", name: "SKIP_REVIEW")
        booleanParam(defaultValue: false, description: "Use Image Cache?", name: "USE_CACHE")
        booleanParam(defaultValue: false, description: "Suppress Telegram Notifications", name: "SUPPRESS_NOTIFS")
        booleanParam(defaultValue: false, description: "Pause Between Steps", name: "Pause")
    }
    stages {
        stage ("Initialization") {
            steps {
                script {
                    echo "Initializing"
                    def skip_manual = params.SKIP_REVIEW
                    def use_cache = params.USE_CACHE
                    if (env.JOB_NAME.contains("PR Builder")) {
                        skip_manual = false
                        use_cache = false
                        echo "PR Build - Forcing manual review and fresh build."
                    } else if (env.JOB_NAME.contains("Branch Builder")) {
                        use_cache = true
                        echo "Branch Build - Using cache for speed."
                    }
                    env.skip_manual_dynamic = skip_manual.toString()
                    env.use_cache_dynamic = use_cache.toString()
                }
            }
        }
        stage ("Tidy Up") { // Cleans up environment to ensure we don't have artifacts from old builds
            steps {
                echo "Removing existing testing containers"
                sh "podman ps -a -q -f ancestor=jenkins-asmrchive | xargs -I {} podman container rm -f {} || true" // Removes all containers that exist under the image
                script {
                    if (env.use_cache_dynamic == "false") {
                        echo "Fresh build - cleaning up old images."
                        sh "podman image prune -a -f || true"
                    }
                }
            }   
        }
        stage ("Build Image") {
            steps {
                echo "Building image (cache: ${env.use_cache_dynamic})"
                script {
                    def cacheFlag = env.use_cache_dynamic == "true" ? "" : "--no-cache --pull"
                    sh "podman --storage-opt ignore_chown_errors=true build ${cacheFlag} -t jenkins-asmrchive ."
                }
            }
        }
        stage ("Spawn Container") {
            steps {
                echo "Constructing Container"
                sh """
                podman create \
                    -p 4445:80 \
                    --name jenkins-asmrchive \
                    -e HOST_URL=http://localhost/ \
                    jenkins-asmrchive
                """
                echo "Starting Container"
                sh "podman container start jenkins-asmrchive"
                script {
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Spawn Container - COMPLETE
                        
                        Container is running at: http://lan.wronghood.net:4445
                        
                        Exec into the container: podman exec -it jenkins-asmrchive bash

                        Click 'Proceed' when ready to continue to Unit Tests.
                        """
                        input(message: pauseMsg)
                    }
                }
            }
        }
        stage ("Unit Tests") {
            steps {
                sh "podman exec jenkins-asmrchive python /var/python_app/test.py"
                script {
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Unit Tests - COMPLETE
                        
                        Container is running at: http://lan.wronghood.net:4445
                        
                        Exec into the container: podman exec -it jenkins-asmrchive bash

                        Click 'Proceed' when ready to continue to Integration Tests.
                        """
                        input(message: pauseMsg)
                    }
                }
            }
        }
        stage ("Integration Tests") {
            steps {
                script {
                    def cacheFlag = env.use_cache_dynamic == "true" ? "" : "--no-cache --pull"
                    sh "podman --storage-opt ignore_chown_errors=true build ${cacheFlag} -t asmrchive-test testing/"
                }
                sh "podman run --network=\"host\" asmrchive-test"
                script {}
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Integration Tests - COMPLETE
                        
                        Container is running at: http://lan.wronghood.net:4445
                        
                        Exec into the container: podman exec -it jenkins-asmrchive bash

                        Click 'Proceed' when ready to continue to DLP Update tests.
                        """
                        input(message: pauseMsg)
                    }
                }
            }
        }
        stage ("Integration Test (DLP Updates)") {
            steps{
                echo "Removing first container"
                sh "podman container stop jenkins-asmrchive"
                sh "podman container rm jenkins-asmrchive"
                echo "Constructing container"
                sh """
                podman create \
                    -p 4445:80 \
                    --name jenkins-asmrchive \
                    -e DLP_VER=2024.12.06 \
                    -e HOST_URL=http://localhost/Jenkins_ASMRchive/ \
                    jenkins-asmrchive
                """
                echo "Starting Container"
                sh "podman container start jenkins-asmrchive"
                sh "podman run \
                        --network=\"host\" \
                        asmrchive-test \
                        --test dlponly"
                script {
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Integration Tests (DLP Updates) - COMPLETE
                        
                        Container is running at: http://lan.wronghood.net:4445
                        
                        Exec into the container: podman exec -it jenkins-asmrchive bash

                        Click 'Proceed' when ready to continue to Manual Review.
                        """
                        input(message: pauseMsg)
                    }
                }
            }
        }
        stage ("Manual Review") {
            when {
                expression {
                    return env.skip_manual_dynamic == "false"
                }
            }
            steps {
                script {
                    // Send a telegram message
                    def baseJenkinsUrl = env.JENKINS_URL
                    def jobNamePath = env.JOB_NAME.replaceAll("/", "/job/")
                    def jobUrl = "${baseJenkinsUrl}job/${jobNamePath}/"
                    def message = "Build requires manual review\n[Jenkins Job](${jobUrl})\n[Live Demo](http://lan.wronghood.net:4445)"
                    def chatId = "222789278"
                    withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN')]) {
                        sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${chatId} -d text='${message}' -d parse_mode=Markdown"
                    }
                    // Prompt user to review the build.
                    def pauseMsg = """
                    Stage: All Tests - COMPLETE
                    
                    Container is running at: http://lan.wronghood.net:4445
                    
                    Exec into the container: podman exec -it jenkins-asmrchive bash

                    Click 'Proceed' when ready to finalize the build.
                    """
                    input(id: 'userInput', message: pauseMsg)
                }
                
            }
        }
    }
    post {
        success {
            script {
                if (!params.SUPPRESS_NOTIFS) {
                    if (!env.JOB_NAME.contains("Daily Master Build")) {
                        def message = "Build Successful: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
                        def chatId = "222789278"
                        withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN')]) {
                            sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${chatId} -d text='${message}'"
                        }
                    }
                }
            }
        }
        failure {
            script {
                if (!params.SUPPRESS_NOTIFS) {
                    def message = "Build Failed: ${env.JOB_NAME} #${env.BUILD_NUMBER}"
                    def chatId = "222789278"
                    withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN')]) {
                        sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${chatId} -d text='${message}'"
                    }
                }
            }
        }
    }
}