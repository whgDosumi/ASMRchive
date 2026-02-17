pipeline {
    agent any
    options {
        // Only keep 10 builds
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }
    environment {
        // Sanitize BUILD_TAG for use in container/image/network/volume names.
        // BUILD_TAG is jenkins-{JOB_NAME}-{BUILD_NUMBER} e.g. jenkins-PR-Builder-42
        BUILD_TAG_CLEAN = "${env.BUILD_TAG.replaceAll('[^a-zA-Z0-9_-]', '-')}"
        // Assign a unique host port based on which executor is running this build.
        // Executors are numbered 0-4, giving ports 4445-4449.
        BUILD_PORT = "${4445 + (env.EXECUTOR_NUMBER as Integer ?: 0)}"
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

                    // Derive unique resource names for this build from BUILD_TAG_CLEAN.
                    // All names are prefixed with 'asmrchive' and use a two-letter
                    // abbreviation to identify the resource type (net, vol).
                    env.APP_IMAGE      = "asmrchive-${env.BUILD_TAG_CLEAN}"
                    env.TEST_IMAGE     = "asmrchive-test-${env.BUILD_TAG_CLEAN}"
                    env.CONTAINER_NAME = "asmrchive-${env.BUILD_TAG_CLEAN}"
                    env.NETWORK_NAME   = "asmrchive-net-${env.BUILD_TAG_CLEAN}"
                    env.VOLUME_NAME    = "asmrchive-vol-${env.BUILD_TAG_CLEAN}"

                    echo "App image:  ${env.APP_IMAGE}"
                    echo "Test image: ${env.TEST_IMAGE}"
                    echo "Container:  ${env.CONTAINER_NAME}"
                    echo "Network:    ${env.NETWORK_NAME}"
                    echo "Volume:     ${env.VOLUME_NAME}"
                    echo "Port:       ${env.BUILD_PORT}"
                }
            }
        }
        stage ("Build Image") {
            steps {
                echo "Building image (cache: ${env.use_cache_dynamic})"
                script {
                    def cacheFlag = env.use_cache_dynamic == "true" ? "" : "--no-cache --pull"
                    sh "podman --storage-opt ignore_chown_errors=true build ${cacheFlag} --label project=asmrchive --label image_type=app -t ${APP_IMAGE} ."
                }
            }
        }
        stage ("Spawn Container") {
            steps {
                echo "Creating podman network: ${NETWORK_NAME}"
                sh "podman network create ${NETWORK_NAME}"
                echo "Creating podman volume: ${VOLUME_NAME}"
                sh "podman volume create --label project=asmrchive ${VOLUME_NAME}"
                echo "Constructing Container on port ${BUILD_PORT}"
                sh """
                podman create \
                    -p ${BUILD_PORT}:80 \
                    --name ${CONTAINER_NAME} \
                    --network ${NETWORK_NAME} \
                    --volume ${VOLUME_NAME}:/var/ASMRchive \
                    --label project=asmrchive \
                    -e HOST_URL=http://localhost/ \
                    ${APP_IMAGE}
                """
                echo "Starting Container"
                sh "podman container start ${CONTAINER_NAME}"
                script {
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Spawn Container - COMPLETE

                        Container is running at: http://lan.wronghood.net:${BUILD_PORT}

                        Exec into the container: podman exec -it ${CONTAINER_NAME} bash

                        Click 'Proceed' when ready to continue to Unit Tests.
                        """
                        input(message: pauseMsg)
                    }
                }
            }
        }
        stage ("Unit Tests") {
            steps {
                sh "podman exec ${CONTAINER_NAME} python /var/python_app/test.py"
                script {
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Unit Tests - COMPLETE

                        Container is running at: http://lan.wronghood.net:${BUILD_PORT}

                        Exec into the container: podman exec -it ${CONTAINER_NAME} bash

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
                    sh "podman --storage-opt ignore_chown_errors=true build ${cacheFlag} --label project=asmrchive --label image_type=test -t ${TEST_IMAGE} testing/"
                }
                sh """
                podman run \
                    --network ${NETWORK_NAME} \
                    ${TEST_IMAGE} \
                    --url http://${CONTAINER_NAME}:80
                """
                script {
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Integration Tests - COMPLETE

                        Container is running at: http://lan.wronghood.net:${BUILD_PORT}

                        Exec into the container: podman exec -it ${CONTAINER_NAME} bash

                        Click 'Proceed' when ready to continue to DLP Update tests.
                        """
                        input(message: pauseMsg)
                    }
                }
            }
        }
        stage ("Integration Test (DLP Updates)") {
            steps {
                echo "Removing first container"
                sh "podman container stop ${CONTAINER_NAME}"
                sh "podman container rm ${CONTAINER_NAME}"
                echo "Constructing container"
                sh """
                podman create \
                    -p ${BUILD_PORT}:80 \
                    --name ${CONTAINER_NAME} \
                    --network ${NETWORK_NAME} \
                    --volume ${VOLUME_NAME}:/var/ASMRchive \
                    --label project=asmrchive \
                    -e DLP_VER=2024.12.06 \
                    -e HOST_URL=http://localhost/Jenkins_ASMRchive/ \
                    ${APP_IMAGE}
                """
                echo "Starting Container"
                sh "podman container start ${CONTAINER_NAME}"
                sh """
                podman run \
                    --network ${NETWORK_NAME} \
                    ${TEST_IMAGE} \
                    --url http://${CONTAINER_NAME}:80 \
                    --test dlponly
                """
                script {
                    if (params.Pause) {
                        def pauseMsg = """
                        Stage: Integration Tests (DLP Updates) - COMPLETE

                        Container is running at: http://lan.wronghood.net:${BUILD_PORT}

                        Exec into the container: podman exec -it ${CONTAINER_NAME} bash

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
                    def message = "Build requires manual review\n[Jenkins Job](${jobUrl})\n[Live Demo](http://lan.wronghood.net:${BUILD_PORT})"
                    def chatId = "222789278"
                    withCredentials([string(credentialsId: 'onion-telegram-token', variable: 'TOKEN')]) {
                        sh "curl -s -X POST https://api.telegram.org/bot${TOKEN}/sendMessage -d chat_id=${chatId} -d text='${message}' -d parse_mode=Markdown"
                    }
                    // Prompt user to review the build.
                    def pauseMsg = """
                    Stage: All Tests - COMPLETE

                    Container is running at: http://lan.wronghood.net:${BUILD_PORT}

                    Exec into the container: podman exec -it ${CONTAINER_NAME} bash

                    Click 'Proceed' when ready to finalize the build.
                    """
                    input(id: 'userInput', message: pauseMsg)
                }
            }
        }
    }
    post {
        always {
            script {
                // Remove this build's container and network.
                // The volume is kept for retention (cleaned up below).
                sh "podman container rm -f ${CONTAINER_NAME} || true"
                sh "podman network rm ${NETWORK_NAME} || true"

                // Keep the 5 most recently built app images; remove older ones.
                // podman images lists newest-first, so tail -n +6 gives us everything
                // beyond the first 5 (i.e. the ones we want to remove).
                sh """
                excess=\$(podman images --filter 'label=project=asmrchive' --filter 'label=image_type=app' --format '{{.ID}}' | tail -n +6)
                if [ -n "\$excess" ]; then echo "\$excess" | xargs podman rmi -f || true; fi
                """

                // Same for test images.
                sh """
                excess=\$(podman images --filter 'label=project=asmrchive' --filter 'label=image_type=test' --format '{{.ID}}' | tail -n +6)
                if [ -n "\$excess" ]; then echo "\$excess" | xargs podman rmi -f || true; fi
                """

                // Keep the 5 most recently created volumes; remove older ones.
                // podman volume ls has no guaranteed sort order, so we inspect each
                // volume's creation time and sort explicitly before trimming.
                sh """
                excess=\$(
                    for vol in \$(podman volume ls --filter 'label=project=asmrchive' --format '{{.Name}}'); do
                        created=\$(podman volume inspect "\$vol" --format '{{.CreatedAt}}')
                        echo "\$created \$vol"
                    done | sort -r | tail -n +6 | awk '{print \$NF}'
                )
                if [ -n "\$excess" ]; then echo "\$excess" | xargs podman volume rm || true; fi
                """
            }
        }
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